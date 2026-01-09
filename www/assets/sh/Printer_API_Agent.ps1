<#
.SYNOPSIS
    資深系統整合工程師實作版本 - Print Server HTTP API & Proactive Monitor
    版本：v9.1 (Business Hours Monitoring Update)
    修正：
    1. 新增時段監控：僅在週一至週五 08:00 - 17:00 執行自動健康檢查。
    2. 維持 v9.0 的佇列堵塞監控、系統過濾、初始離線排除、解析器相容性。
    3. 完全相容 PowerShell 2.0 (Windows Server 2008 SP2) 至 2019。
#>

# -------------------------------------------------------------------------
# 1. 基礎設定區
# -------------------------------------------------------------------------
$port               = 8888
$apiKey             = "YourSecretApiKey123"      
$logPath            = "C:\Temp"
$maxLogSizeBytes    = 10MB                       
$maxHistory         = 5                          

$notifyIp           = "220.1.34.75"
$notifyEndpoint     = "/api/notification_json_api.php"
$notifyUrl          = "http://$notifyIp$notifyEndpoint"
$notifyChannels     = @("HA10013859")

# --- 監控頻率與告警門檻 ---
$checkIntervalSec   = 60                  # 巡檢間隔 60 秒
$errorThreshold     = 5                   # 狀態異常連續 5 次才送通知

# --- 監控時段設定 (週一至週五 08:00-17:00) ---
$monitorStartHour   = 8
$monitorEndHour     = 17
$monitorDays        = @("Monday", "Tuesday", "Wednesday", "Thursday", "Friday")

# --- 佇列監控設定 ---
$queueThreshold     = 20                  
$queueStuckLimit    = 5                   

# 虛擬印表機過濾關鍵字
$excludeKeywords    = @(
    "Microsoft Print to PDF",
    "Microsoft XPS Document Writer",
    "Fax",
    "OneNote",
    "Send To OneNote",
    "Microsoft Shared Fax Driver"
)

# 全局狀態變數
$global:PrinterStateCache   = New-Object System.Collections.Hashtable
$global:PrinterErrorCount   = New-Object System.Collections.Hashtable 
$global:ExcludedPrinters    = New-Object System.Collections.Hashtable 
$global:IsFirstRun          = $true

# 佇列監控專用狀態表
$global:QueueStuckCount     = New-Object System.Collections.Hashtable 
$global:LastQueueCount      = New-Object System.Collections.Hashtable 

# -------------------------------------------------------------------------
# 2. 核心函數庫 (PS 2.0 底層相容)
# -------------------------------------------------------------------------

function ConvertTo-SimpleJson {
    param($InputObject)
    if ($null -eq $InputObject) { return "null" }
    if ($InputObject -is [string]) {
        $escaped = $InputObject.Replace('\', '\\').Replace('"', '\"')
        return """$escaped"""
    }
    if ($InputObject -is [System.Boolean]) {
        if ($InputObject) { return "true" } else { return "false" }
    }
    if ($InputObject -is [System.ValueType]) {
        return $InputObject.ToString().ToLower()
    }
    $type = $InputObject.GetType()
    if ($null -ne $type.GetInterface("IDictionary")) {
        $pairs = New-Object System.Collections.Generic.List[string]
        foreach ($key in $InputObject.Keys) {
            $val = $InputObject[$key]
            $pairs.Add("""$key"":" + (ConvertTo-SimpleJson $val))
        }
        return "{" + [string]::Join(",", $pairs) + "}"
    }
    if ($null -ne $type.GetInterface("IEnumerable")) {
        $elements = New-Object System.Collections.Generic.List[string]
        foreach ($item in $InputObject) {
            $elements.Add((ConvertTo-SimpleJson $item))
        }
        return "[" + [string]::Join(",", $elements) + "]"
    }
    $objPairs = New-Object System.Collections.Generic.List[string]
    try {
        foreach ($prop in $InputObject.PSObject.Properties) {
            $n = $prop.Name
            $v = $prop.Value
            $objPairs.Add("""$n"":" + (ConvertTo-SimpleJson $v))
        }
    } catch {
        return """$($InputObject.ToString())"""
    }
    if ($objPairs.Count -gt 0) {
        return "{" + [string]::Join(",", $objPairs) + "}"
    }
    return """$($InputObject.ToString())"""
}

function Write-ApiLog {
    param([string]$message)
    try {
        if (-not (Test-Path $logPath)) { [void](New-Item -ItemType Directory -Path $logPath -Force) }
        $today = Get-Date -Format "yyyy-MM-dd"
        $fullPath = Join-Path $logPath "PrintApi_$today.log"
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        $logEntry = "[$timestamp] $message"
        if (Test-Path $fullPath) {
            $file = Get-Item $fullPath
            if ($file.Length -ge $maxLogSizeBytes) {
                if (Test-Path "$fullPath.$maxHistory") { Remove-Item "$fullPath.$maxHistory" -Force }
                for ($i = $maxHistory - 1; $i -ge 1; $i--) {
                    $src = "$fullPath.$i"; $dest = "$fullPath.$($i + 1)"
                    if (Test-Path $src) { Move-Item $src $dest -Force }
                }
                Move-Item $fullPath "$fullPath.1" -Force
            }
        }
        Add-Content -Path $fullPath -Value $logEntry
    } catch {}
}

function Send-SysAdminNotify {
    param([Parameter(Mandatory=$true)][string]$content, [string]$title = "印表機系統通知")
    try {
        $localIp = "127.0.0.1"
        $configs = Get-WmiObject Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled -and $_.IPAddress }
        if ($null -ne $configs) {
            $firstConfig = if ($configs -is [array]) { $configs[0] } else { $configs }
            if ($null -ne $firstConfig -and $null -ne $firstConfig.IPAddress) { $localIp = $firstConfig.IPAddress[0] }
        }
        $fields = @{ "type"="add_notification"; "title"=$title; "content"=$content; "priority"="3"; "sender"="$($env:COMPUTERNAME) ($localIp)"; "from_ip"=$localIp }
        $encodedParts = New-Object System.Collections.Generic.List[string]
        foreach ($key in $fields.Keys) { $encodedParts.Add("$key=$([System.Uri]::EscapeDataString($fields[$key]))") }
        if ($null -ne $notifyChannels) { foreach ($chan in $notifyChannels) { $encodedParts.Add("channels[]=$([System.Uri]::EscapeDataString($chan))") } }
        $postBody = [string]::Join("&", $encodedParts)

        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("Content-Type", "application/x-www-form-urlencoded")
        $wc.Encoding = [System.Text.Encoding]::UTF8
        Write-ApiLog ">>> [通知稽核] 標題: $title | 內容: $content"
        [void]$wc.UploadString($notifyUrl, "POST", $postBody)
        Write-ApiLog ">>> [通知成功] 訊息傳送完成。"
    } catch {
        Write-ApiLog ">>> [通知失敗] 錯誤: $($_.Exception.Message)"
    }
}

function Get-PrinterStatusData {
    $results = New-Object System.Collections.Generic.List[Object]
    $wmiPrinters = Get-WmiObject -Class Win32_Printer
    foreach ($p in $wmiPrinters) {
        $pName = $p.Name
        $shouldSkip = $false
        foreach ($keyword in $excludeKeywords) {
            if ($pName -like "*$keyword*") { $shouldSkip = $true; break }
        }
        if ($shouldSkip) { continue }

        $status = "Unknown"
        if ($p.WorkOffline) { $status = "Offline" }
        else {
            switch ($p.PrinterStatus) {
                1 { $status = "Error" }
                2 { $status = "Error" }
                3 { $status = "Ready" }
                4 { $status = "Printing" }
                5 { $status = "Warmup" }
                7 { $status = "Offline" }
                default { $status = "Warning/Busy" }
            }
        }
        $obj = New-Object PSObject
        $obj | Add-Member NoteProperty Name $pName
        $obj | Add-Member NoteProperty Status $status
        $obj | Add-Member NoteProperty Jobs $p.JobCount
        $obj | Add-Member NoteProperty Port $p.PortName
        $results.Add($obj)
    }
    return $results
}

function Test-PrinterHealth {
    Write-ApiLog ">>> [監控] 開始健康檢查巡檢..."
    $printers = Get-PrinterStatusData
    $batchAlerts = New-Object System.Collections.Generic.List[string]
    
    foreach ($p in $printers) {
        $name = $p.Name; $pStatus = $p.Status.ToString(); $pJobs = $p.Jobs
        
        # --- 1. 初始排除邏輯 ---
        if ($global:IsFirstRun) {
            if ($pStatus -eq "Offline") { $global:ExcludedPrinters[$name] = $true }
            else { $global:PrinterStateCache[$name] = "OK" }
            $global:LastQueueCount[$name] = $pJobs
            $global:QueueStuckCount[$name] = 0
            continue
        }

        # --- 2. 處理初始排除重納監控 ---
        if ($global:ExcludedPrinters.ContainsKey($name)) {
            if ($pStatus -eq "Ready" -or $pStatus -eq "Printing" -or $pStatus -eq "Warmup") {
                $global:ExcludedPrinters.Remove($name)
                $global:PrinterStateCache[$name] = "OK"
                $global:PrinterErrorCount[$name] = 0
                Write-ApiLog ">>> [監控] $name 已上線，重新開始監控。"
            }
            continue
        }

        # --- 3. 狀態異常監控 (Error/Warning) ---
        $isErrorState = ($pStatus -eq "Error") -or ($pStatus -eq "Warning/Busy")
        $errCount = 0
        if ($global:PrinterErrorCount.ContainsKey($name)) { $errCount = $global:PrinterErrorCount[$name] }

        if ($isErrorState) {
            $errCount++
            $global:PrinterErrorCount[$name] = $errCount
            Write-ApiLog ">>> [監控] $name 異常計數: $errCount"
            if ($errCount -eq $errorThreshold) {
                $batchAlerts.Add("● [狀態告警] 印表機 [$name] 連續異常。目前狀態: $pStatus")
                $global:PrinterStateCache[$name] = "ERROR"
            }
        } else {
            if ($errCount -ge $errorThreshold) {
                $msgSuffix = if ($pStatus -eq "Offline") { "已離線" } else { "已恢復正常" }
                $batchAlerts.Add("○ [恢復] 印表機 [$name] $msgSuffix。")
            }
            $global:PrinterErrorCount[$name] = 0
        }

        # --- 4. 佇列堵塞監控 ---
        $lastJobs = 0
        if ($global:LastQueueCount.ContainsKey($name)) { $lastJobs = $global:LastQueueCount[$name] }
        $stuckCount = 0
        if ($global:QueueStuckCount.ContainsKey($name)) { $stuckCount = $global:QueueStuckCount[$name] }

        if ($pJobs -ge $queueThreshold) {
            if ($pJobs -ge $lastJobs) {
                $stuckCount++
                $global:QueueStuckCount[$name] = $stuckCount
                Write-ApiLog ">>> [佇列監控] $name 佇列累積: $pJobs 案 (連續未減少: $stuckCount 次)"
                if ($stuckCount -eq $queueStuckLimit) {
                    $batchAlerts.Add("?? [佇列堵塞] 印表機 [$name] 佇列堆積且長時間未減少 ($pJobs 案)。")
                }
            } else { $global:QueueStuckCount[$name] = 0 }
        } else { $global:QueueStuckCount[$name] = 0 }
        $global:LastQueueCount[$name] = $pJobs
    }
    
    if ($global:IsFirstRun) {
        Write-ApiLog ">>> [監控] 初始基準建立完成。"
        $global:IsFirstRun = $false
        return
    }

    if ($batchAlerts.Count -gt 0) { 
        Send-SysAdminNotify -content ([string]::Join("`n", $batchAlerts)) -title "印表機維護摘要" 
    }
}

# -------------------------------------------------------------------------
# 3. 主程序 (HttpListener 持久監聽)
# -------------------------------------------------------------------------
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
try {
    $listener.Start()
    Write-ApiLog "--- API 伺服器 v9.1 上線 (工作時段監控已啟用) ---"
} catch {
    Write-ApiLog "!!! [錯誤] 啟動失敗: $($_.Exception.Message)"; exit
}

$nextCheckTime = Get-Date
$nextHeartbeatTime = Get-Date
$contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        
        # A. 存活心跳紀錄 (每 60 秒)
        if ($now -ge $nextHeartbeatTime) {
            Write-ApiLog "[存活檢查] 伺服器運作中。監聽埠口: $port"
            $nextHeartbeatTime = $now.AddSeconds(60)
        }
        
        # B. 定時健康檢查 (含時段判斷)
        if ($now -ge $nextCheckTime) {
            # 判斷是否在監控時段內 (週一至五, 08-17)
            $dayOfWeek = $now.DayOfWeek.ToString()
            $hour = $now.Hour
            
            $isMonitorDay = $false
            foreach ($d in $monitorDays) { if ($d -eq $dayOfWeek) { $isMonitorDay = $true; break } }
            
            $isMonitorHour = ($hour -ge $monitorStartHour) -and ($hour -lt $monitorEndHour)
            
            if ($isMonitorDay -and $isMonitorHour) {
                Test-PrinterHealth
            } else {
                Write-ApiLog ">>> [監控時段] 目前非辦公時段 ($dayOfWeek $hour:00)，跳過自動巡檢。"
            }
            $nextCheckTime = (Get-Date).AddSeconds($checkIntervalSec)
        }

        # C. HTTP API 請求處理
        if ($null -eq $contextTask) { $contextTask = $listener.BeginGetContext($null, $null) }
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { continue }

        $context = $listener.EndGetContext($contextTask)
        $contextTask = $null 
        
        $request  = $context.Request
        $response = $context.Response
        $clientIP = $request.RemoteEndPoint.ToString()
        $urlPath  = $request.Url.AbsolutePath.ToLower()
        Write-ApiLog ">>> [連線] 來自: $clientIP | 路徑: $urlPath"

        $resultData = New-Object System.Collections.Hashtable
        $resultData["success"] = $false
        $resultData["message"] = ""
        $resultData["data"]    = $null

        $incomingKey = $request.Headers["X-API-KEY"]
        if ($incomingKey -ne $apiKey) {
            $response.StatusCode = 401
            $resultData["message"] = "Unauthorized"
        } else {
            if ($urlPath -eq "/printers") { 
                $resultData["data"] = Get-PrinterStatusData; $resultData["success"] = $true 
            }
            elseif ($urlPath -eq "/printer/status") {
                $pName = $request.QueryString["name"]
                $target = Get-PrinterStatusData | Where-Object { $_.Name -eq $pName }
                if ($null -ne $target) { $resultData["data"] = $target; $resultData["success"] = $true }
                else { $resultData["message"] = "Printer not found" }
            }
            elseif ($urlPath -eq "/printer/refresh") {
                $pName = $request.QueryString["name"]
                $pObj = Get-WmiObject -Class Win32_Printer | Where-Object { $_.Name -eq $pName }
                if ($null -ne $pObj) {
                    $pObj.Pause(); Start-Sleep -Milliseconds 500; $pObj.Resume()
                    $resultData["success"] = $true
                    Send-SysAdminNotify -content "API：印表機 [$pName] 手動重新整理。" -title "維護操作"
                } else { $resultData["message"] = "Printer not found" }
            }
            elseif ($urlPath -eq "/printer/clear") {
                $pName = $request.QueryString["name"]
                $jobs = Get-WmiObject -Class Win32_PrintJob | Where-Object { $_.Name -like "*$pName*" }
                $jobCount = if ($null -eq $jobs) { 0 } elseif ($jobs -is [array]) { $jobs.Count } else { 1 }
                if ($jobCount -gt 0) { foreach ($job in $jobs) { $job.Delete() } }
                $resultData["success"] = $true
                Send-SysAdminNotify -content "API：印表機 [$pName] 佇列已清除。" -title "隊列操作"
            }
            elseif ($urlPath -eq "/service/restart-spooler") {
                try {
                    Restart-Service "Spooler" -Force
                    $resultData["success"] = $true
                    Send-SysAdminNotify -content "API：Spooler 服務已成功重啟。" -title "服務操作"
                } catch { Write-ApiLog "!!! [服務操作失敗] $($_.Exception.Message)" }
            }
            else { $response.StatusCode = 404; $resultData["message"] = "Not Found" }
        }

        $jsonResponse = ConvertTo-SimpleJson $resultData
        $buffer = [System.Text.Encoding]::UTF8.GetBytes($jsonResponse)
        $response.ContentType = "application/json; charset=utf-8"
        $response.OutputStream.Write($buffer, 0, $buffer.Length)
        $response.Close()
        Write-ApiLog "<<< [完成] $clientIP 的請求處理週期結束。"
    } catch {
        Write-ApiLog "!!! [重大錯誤] $($_.Exception.Message)"
        if ($null -ne $contextTask) { $contextTask = $null } 
    }
}