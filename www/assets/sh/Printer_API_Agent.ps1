<#
.SYNOPSIS
    資深系統整合工程師實作版本 - Print Server HTTP API & Proactive Monitor
    版本：v14.5 (Power Status Detection via ICMP)
    修正：
    1. 新增網路存活偵測：利用 ICMP Ping 區分「真正斷電」與「軟體離線」。
    2. 優化狀態描述：依據 Ping 結果回傳 "可能未開機" 或 "網路通暢" 等精確資訊。
    3. 維持 v14.4 的所有功能：PDF 上傳、雙面列印、自癒通知、日誌清理與 PS 2.0 相容。
    4. 完全相容 PowerShell 2.0 (Windows Server 2008 SP2) 至 2019。
.NOTES
    ?? 測試指令
    curl -H "X-API-KEY: %API_KEY%" http://%SERVER_IP%:8888/printers
#>

# -------------------------------------------------------------------------
# 1. 基礎設定區
# -------------------------------------------------------------------------
$port               = 8888
$apiKey             = "YourSecretApiKey123"      
$logPath            = "C:\Temp"
$uploadPath         = "C:\Temp\Uploads"           
$maxLogSizeBytes    = 10MB                       
$maxHistory         = 5                          
$logRetentionDays   = 7                   

# --- PDF 閱讀器路徑清單 ---
$pdfReaderPaths     = @(
    "C:\Program Files (x86)\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe",
    "C:\Program Files\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe",
    "C:\FoxitReader\Foxit Reader.exe",
    "C:\Program Files\Adobe\Acrobat DC\Acrobat\Acrobat.exe",
    "C:\Program Files (x86)\Adobe\Acrobat Reader DC\Reader\AcroRd32.exe"
)

$notifyIp           = "220.1.34.75"
$notifyEndpoint     = "/api/notification_json_api.php"
$notifyUrl          = "http://$notifyIp$notifyEndpoint"
$notifyChannels     = @("HA10013859")

# --- 監控時段與頻率 ---
$checkIntervalSec   = 60                  
$errorThreshold     = 5                   
$monitorStartHour   = 8
$monitorEndHour     = 17
$monitorDays        = @("Monday", "Tuesday", "Wednesday", "Thursday", "Friday")

# --- 智慧自癒設定 ---
$enableAutoCleanup  = $true               
$zombieTimeMinutes  = 10                  
$enableAutoHeal     = $true               
$maxStuckPrinters   = 3                   

# --- 佇列監控設定 ---
$queueThreshold     = 20                  
$queueStuckLimit    = 5                   

# --- 印表機排除設定 ---
$excludeKeywords    = @("PDF", "XPS", "Fax", "OneNote", "Microsoft Shared Fax")
$manualExcludePrinters = @(
    "範例印表機名稱_A",
    "範例印表機名稱_B"
)

# 全局狀態變數
$global:PrinterStateCache   = New-Object System.Collections.Hashtable
$global:PrinterErrorCount   = New-Object System.Collections.Hashtable 
$global:ExcludedPrinters    = New-Object System.Collections.Hashtable 
$global:QueueStuckCount     = New-Object System.Collections.Hashtable 
$global:LastQueueCount      = New-Object System.Collections.Hashtable 
$global:IsFirstRun          = $true
$global:ValidPdfReader      = $null

# -------------------------------------------------------------------------
# 2. 核心函數庫
# -------------------------------------------------------------------------

function ConvertTo-SimpleJson {
    param($InputObject)
    if ($null -eq $InputObject) { return "null" }
    if ($InputObject -is [string]) { return """$($InputObject.Replace('\', '\\').Replace('"', '\"'))""" }
    if ($InputObject -is [System.Boolean]) { if ($InputObject) { return "true" } else { return "false" } }
    if ($InputObject -is [System.ValueType]) { return $InputObject.ToString().ToLower() }
    
    $type = $InputObject.GetType()
    if ($null -ne $type.GetInterface("IDictionary")) {
        $pairs = New-Object System.Collections.Generic.List[string]
        foreach ($key in $InputObject.Keys) { $pairs.Add("""$key"":" + (ConvertTo-SimpleJson $InputObject[$key])) }
        return "{" + [string]::Join(",", $pairs) + "}"
    }
    if ($null -ne $type.GetInterface("IEnumerable")) {
        $elements = New-Object System.Collections.Generic.List[string]
        foreach ($item in $InputObject) { $elements.Add((ConvertTo-SimpleJson $item)) }
        return "[" + [string]::Join(",", $elements) + "]"
    }
    
    $objPairs = New-Object System.Collections.Generic.List[string]
    try {
        foreach ($prop in $InputObject.PSObject.Properties) { $objPairs.Add("""$($prop.Name)"":" + (ConvertTo-SimpleJson $prop.Value)) }
    } catch { return """$($InputObject.ToString())""" }
    
    if ($objPairs.Count -gt 0) { 
        return "{" + [string]::Join(",", $objPairs) + "}" 
    } else { 
        return """$($InputObject.ToString())""" 
    }
}

function Write-ApiLog {
    param([string]$message)
    try {
        if (-not (Test-Path $logPath)) { [void](New-Item -ItemType Directory -Path $logPath -Force) }
        if (-not (Test-Path $uploadPath)) { [void](New-Item -ItemType Directory -Path $uploadPath -Force) } 
        
        $today = Get-Date -Format "yyyy-MM-dd"
        $fullPath = Join-Path $logPath "PrintApi_$today.log"
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
        
        if (Test-Path $fullPath) {
            if ((Get-Item $fullPath).Length -ge $maxLogSizeBytes) {
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

function Cleanup-OldLogs {
    try {
        if (Test-Path $logPath) {
            $limitDate = (Get-Date).AddDays(-$logRetentionDays)
            $oldFiles = Get-ChildItem -Path $logPath -Filter "PrintApi_*.log*" | Where-Object { $_.LastWriteTime -lt $limitDate }
            if ($null -ne $oldFiles) {
                foreach ($file in $oldFiles) {
                    Write-ApiLog ">>> [日誌清理] 刪除過期日誌: $($file.Name)"
                    Remove-Item $file.FullName -Force
                }
            }
            if (Test-Path $uploadPath) {
                $oldPdfs = Get-ChildItem -Path $uploadPath -Filter "*.pdf" | Where-Object { $_.CreationTime -lt (Get-Date).AddDays(-1) }
                if ($null -ne $oldPdfs) { foreach ($pdf in $oldPdfs) { Remove-Item $pdf.FullName -Force } }
            }
        }
    } catch {}
}

function Send-SysAdminNotify {
    param([Parameter(Mandatory=$true)][string]$content, [string]$title = "印表機系統通知")
    try {
        $localIp = "127.0.0.1"
        try {
            $ipConfig = Get-WmiObject Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled -and $_.IPAddress }
            if ($null -ne $ipConfig) {
                if ($ipConfig -is [array]) { $localIp = $ipConfig[0].IPAddress[0] } else { $localIp = $ipConfig.IPAddress[0] }
            }
        } catch { }
        $fields = @{ "type"="add_notification"; "title"=$title; "content"=$content; "priority"="3"; "sender"="$($env:COMPUTERNAME) ($localIp)"; "from_ip"=$localIp }
        $encodedParts = New-Object System.Collections.Generic.List[string]
        foreach ($key in $fields.Keys) { $encodedParts.Add("$key=$([System.Uri]::EscapeDataString($fields[$key]))") }
        if ($null -ne $notifyChannels) { foreach ($chan in $notifyChannels) { $encodedParts.Add("channels[]=$([System.Uri]::EscapeDataString($chan))") } }
        $postBody = [string]::Join("&", $encodedParts)
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add("Content-Type", "application/x-www-form-urlencoded")
        $wc.Encoding = [System.Text.Encoding]::UTF8
        Write-ApiLog ">>> [準備發送通知] 標題: $title"
        [void]$wc.UploadString($notifyUrl, "POST", $postBody)
        Write-ApiLog ">>> [通知發送成功]"
    } catch { Write-ApiLog "!!! [通知發送失敗] $($_.Exception.Message)" }
}

function Invoke-SpoolerSelfHealing {
    param([string]$reason)
    Write-ApiLog "!!! [自癒啟動] $reason"
    $startMsg = "系統偵測到嚴重異常 ($reason)，正在自動執行深度修復流程。"
    Send-SysAdminNotify -title "?? 系統自動自癒啟動" -content $startMsg
    try {
        Stop-Service "Spooler" -Force; Start-Sleep -Seconds 3
        $spoolPath = "C:\Windows\System32\spool\PRINTERS"
        if (Test-Path $spoolPath) { Get-ChildItem -Path "$spoolPath\*" -Include *.* -Force | Remove-Item -Force }
        Start-Service "Spooler"
        Send-SysAdminNotify -title "? 系統自動自癒完成" -content "服務已重啟並清理暫存檔。"
    } catch { Send-SysAdminNotify -title "? 系統自動自癒失敗" -content "錯誤: $($_.Exception.Message)" }
}

function Get-PrinterStatusData {
    $results = New-Object System.Collections.Generic.List[Object]
    
    $portMap = @{}
    try {
        $tcpPorts = Get-WmiObject -Class Win32_TCPIPPrinterPort -ErrorAction SilentlyContinue
        if ($null -ne $tcpPorts) {
            foreach ($tp in $tcpPorts) {
                if ($null -ne $tp.Name) { $portMap[$tp.Name] = $tp.HostAddress }
            }
        }
    } catch {}

    $wmiPrinters = Get-WmiObject -Class Win32_Printer
    
    # 初始化 Ping 物件 (複用以節省資源)
    $pingSender = New-Object System.Net.NetworkInformation.Ping

    foreach ($p in $wmiPrinters) {
        $pName = $p.Name; $shouldSkip = $false
        foreach ($kw in $excludeKeywords) { if ($pName -like "*$kw*") { $shouldSkip=$true; break } }
        if ($shouldSkip) { continue }
        foreach ($exName in $manualExcludePrinters) { if ($pName -eq $exName) { $shouldSkip=$true; break } }
        if ($shouldSkip) { continue }

        $errorList = New-Object System.Collections.Generic.List[string]
        $isOffline = $false; $isHardwareError = $false
        if ($p.WorkOffline) { $isOffline = $true }
        $errState = $p.DetectedErrorState
        if ($null -ne $errState) {
            if (($errState -band 16) -eq 16) { $errorList.Add("缺紙"); $isHardwareError = $true }
            if (($errState -band 128) -eq 128) { $errorList.Add("機蓋開啟"); $isHardwareError = $true }
            if (($errState -band 256) -eq 256) { $errorList.Add("夾紙"); $isHardwareError = $true }
            if (($errState -band 512) -eq 512) { $isOffline = $true }
            if (($errState -band 1024) -eq 1024) { $errorList.Add("硬體故障"); $isHardwareError = $true }
        }
        
        $finalStatus = "Ready"
        if ($isHardwareError) { 
            $finalStatus = "Error (" + [string]::Join(", ", $errorList) + ")" 
        } elseif ($isOffline) { 
            $finalStatus = "Offline" 
        } else {
            switch ($p.PrinterStatus) {
                1 { $finalStatus = "Error (未知 - 可能原因: 驅動限制/SNMP受阻/特殊硬體狀態)" }
                2 { $finalStatus = "Error (其他 - 請檢查設備面板)" }
                4 { $finalStatus = "Printing" }
                5 { $finalStatus = "Warmup" }
                default { $finalStatus = "Ready" }
            }
        }
        
        # --- IP 解析與存活偵測 ---
        $pPort = $p.PortName
        $pIP = ""
        if ($portMap.ContainsKey($pPort)) { $pIP = $portMap[$pPort] } else { $pIP = $pPort }
        
        # [新增] 針對 IPv4 進行快速 Ping 檢測 (Timeout 200ms)
        if ($pIP -match "^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$") {
            try {
                $reply = $pingSender.Send($pIP, 200)
                if ($reply.Status -ne "Success") {
                    # Ping 失敗
                    if ($finalStatus -eq "Offline") {
                        $finalStatus = "Offline (無回應 - 可能未開機)"
                    } elseif ($finalStatus -like "Ready*") {
                        $finalStatus = "Warning (無回應 - 可能斷線或未開機)"
                    }
                } else {
                    # Ping 成功
                    if ($finalStatus -eq "Offline") {
                        $finalStatus = "Offline (軟體離線 - 網路通暢)"
                    }
                }
            } catch { 
                # Ping 發生錯誤 (如 DNS 解析失敗)
                if ($finalStatus -eq "Offline") { $finalStatus = "Offline (網路錯誤)" }
            }
        }

        $obj = New-Object PSObject
        $obj | Add-Member NoteProperty Name $pName
        $obj | Add-Member NoteProperty Status $finalStatus
        $obj | Add-Member NoteProperty Jobs $p.JobCount
        $obj | Add-Member NoteProperty IP $pIP
        $results.Add($obj)
    }
    return $results
}

function Test-PrinterHealth {
    Cleanup-OldLogs
    Write-ApiLog ">>> [監控] 巡檢開始..."
    $printers = Get-PrinterStatusData
    $batchAlerts = New-Object System.Collections.Generic.List[string]
    $stuckPrinters = 0
    if ($enableAutoCleanup) {
        $zombies = Get-WmiObject -Class Win32_PrintJob | Where-Object { ($_.JobStatus -like "*Error*" -or $_.JobStatus -like "*Deleting*") }
        if ($null -ne $zombies) {
            foreach ($z in $zombies) { $batchAlerts.Add("?? [自癒] 清理卡住作業: $($z.JobId)"); $z.Delete() }
        }
    }
    foreach ($p in $printers) {
        $name = $p.Name; $pStatus = $p.Status.ToString(); $pJobs = $p.Jobs
        if ($global:IsFirstRun) {
            if ($pStatus -like "Offline*") { $global:ExcludedPrinters[$name] = $true }
            $global:LastQueueCount[$name] = $pJobs; continue
        }
        if ($global:ExcludedPrinters.ContainsKey($name)) {
            if ($pStatus -like "Ready*" -or $pStatus -eq "Printing") { $global:ExcludedPrinters.Remove($name) }
            continue
        }
        if ($pStatus -like "Error*") {
            $global:PrinterErrorCount[$name]++
            if ($global:PrinterErrorCount[$name] -eq $errorThreshold) { $batchAlerts.Add("● [異常] 印表機 [$name] $pStatus") }
        } else {
            if ($global:PrinterErrorCount[$name] -ge $errorThreshold) { $batchAlerts.Add("○ [恢復] 印表機 [$name] 已恢復正常。") }
            $global:PrinterErrorCount[$name] = 0
        }
        if ($pJobs -ge $queueThreshold -and $pJobs -ge $global:LastQueueCount[$name]) {
            $global:QueueStuckCount[$name]++
            if ($global:QueueStuckCount[$name] -eq $queueStuckLimit) {
                $batchAlerts.Add("?? [堵塞] 印表機 [$name] 佇列停滯 ($pJobs 案)。")
                $stuckPrinters++
            }
        } else { $global:QueueStuckCount[$name] = 0 }
        $global:LastQueueCount[$name] = $pJobs
    }
    if ($enableAutoHeal -and $stuckPrinters -ge $maxStuckPrinters) { Invoke-SpoolerSelfHealing -reason "多台印表機同時堵塞"; return }
    if ($global:IsFirstRun) { $global:IsFirstRun = $false; return }
    # 停用維護摘要通知: if ($batchAlerts.Count -gt 0) { Send-SysAdminNotify -content ([string]::Join("`n", $batchAlerts)) -title "印表機維護摘要" }
}

# -------------------------------------------------------------------------
# 3. 主程序 (HttpListener)
# -------------------------------------------------------------------------
Write-ApiLog "--- 系統初始化: 正在搜尋 PDF 閱讀器 ---"
foreach ($path in $pdfReaderPaths) {
    if (Test-Path $path) {
        $global:ValidPdfReader = $path
        Write-ApiLog "[系統初始化] 已鎖定 PDF 閱讀器: $path"
        break
    }
}
if ($null -eq $global:ValidPdfReader) {
    Write-ApiLog "[系統初始化] 警告: 未偵測到指定清單中的閱讀器，後續列印將降級使用系統預設關聯。"
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
try { $listener.Start(); Write-ApiLog "--- 伺服器 v14.5 上線 (Ping 存活偵測功能已啟用) ---" } catch { exit }

$nextCheck = Get-Date; $nextHeart = Get-Date; $contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        if ($now -ge $nextHeart) { Write-ApiLog "[存活] 監聽中..."; $nextHeart = $now.AddSeconds(60) }
        if ($now -ge $nextCheck) {
            $day = $now.DayOfWeek.ToString()
            if (($now.Hour -ge $monitorStartHour) -and ($now.Hour -lt $monitorEndHour) -and ($monitorDays -contains $day)) {
                Test-PrinterHealth
            } else { Write-ApiLog ">>> [非工作時段] 跳過巡檢。" }
            $nextCheck = $now.AddSeconds($checkIntervalSec)
        }

        if ($null -eq $contextTask) { $contextTask = $listener.BeginGetContext($null, $null) }
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { continue }

        $context = $listener.EndGetContext($contextTask); $contextTask = $null
        $request = $context.Request; $response = $context.Response; $path = $request.Url.AbsolutePath.ToLower()
        Write-ApiLog ">>> [請求] 來自: $($request.RemoteEndPoint) 路徑: $path"

        # --- [CORS] ---
        $response.AddHeader("Access-Control-Allow-Origin", "*")
        $response.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $response.AddHeader("Access-Control-Allow-Headers", "*") 

        if ($request.HttpMethod -eq "OPTIONS") {
            $response.StatusCode = 200; $response.Close()
            Write-ApiLog ">>> [CORS] 預檢請求通過"; continue
        }

        $res = @{ "success"=$false; "message"=""; "data"=$null }
        if ($request.Headers["X-API-KEY"] -ne $apiKey) { $response.StatusCode = 401 }
        else {
            if ($path -eq "/printers") { 
                $res.data = Get-PrinterStatusData; $res.success = $true 
            }
            elseif ($path -eq "/server/logs") {
                $todayLog = Join-Path $logPath "PrintApi_$(Get-Date -Format 'yyyy-MM-dd').log"
                if (Test-Path $todayLog) {
                    $linesReq = $request.QueryString["lines"]
                    $count = 100
                    if ($null -ne $linesReq -and $linesReq -match "^\d+$") { $count = [int]$linesReq }
                    $logContent = Get-Content $todayLog | Select-Object -Last $count
                    $res.data = $logContent; $res.success = $true; $res.message = "已讀取最後 $count 行"
                } else { $res.message = "今日尚無日誌檔案" }
            }
            elseif ($path -eq "/printer/print-pdf") {
                if ($request.HttpMethod -eq "POST") {
                    $pName = $request.QueryString["name"]
                    $pObj = Get-WmiObject Win32_Printer | Where-Object { $_.Name -eq $pName }
                    
                    if ($null -ne $pObj) {
                        $duplexReq = $request.QueryString["duplex"]
                        $restoreDuplex = $false; $oldDuplexMode = $null
                        
                        if ($null -ne $duplexReq) {
                            if (Get-Command Set-PrintConfiguration -ErrorAction SilentlyContinue) {
                                try {
                                    $currentCfg = Get-PrintConfiguration -PrinterName $pName -ErrorAction Stop
                                    $oldDuplexMode = $currentCfg.DuplexingMode
                                    $targetMode = "OneSided"
                                    if ($duplexReq -eq "1" -or $duplexReq -eq "long") { $targetMode = "TwoSidedLongEdge" }
                                    elseif ($duplexReq -eq "2" -or $duplexReq -eq "short") { $targetMode = "TwoSidedShortEdge" }
                                    
                                    if ($oldDuplexMode -ne $targetMode) {
                                        Write-ApiLog ">>> [設定] 切換雙面模式: $targetMode"
                                        Set-PrintConfiguration -PrinterName $pName -DuplexingMode $targetMode -ErrorAction Stop
                                        $restoreDuplex = $true
                                    }
                                } catch { Write-ApiLog ">>> [設定警告] 無法變更雙面設定: $($_.Exception.Message)" }
                            } else { Write-ApiLog ">>> [忽略] 不支援 Set-PrintConfiguration" }
                        }

                        $fileName = "Upload_$(Get-Date -Format 'yyyyMMdd_HHmmss').pdf"
                        $savePath = Join-Path $uploadPath $fileName
                        Write-ApiLog ">>> [上傳] 接收 PDF: $fileName"
                        
                        $fs = New-Object System.IO.FileStream($savePath, [System.IO.FileMode]::Create)
                        $buffer = New-Object byte[] 8192
                        do {
                            $read = $request.InputStream.Read($buffer, 0, $buffer.Length)
                            if ($read -gt 0) { $fs.Write($buffer, 0, $read) }
                        } while ($read -gt 0); $fs.Close()
                        
                        Write-ApiLog ">>> [列印] 調用 PDF 閱讀器..."
                        try {
                            if ($null -ne $global:ValidPdfReader) {
                                Write-ApiLog ">>> [列印] 使用快取路徑: $($global:ValidPdfReader)"
                                $argList = "/t ""$savePath"" ""$pName"""
                                $proc = Start-Process -FilePath $global:ValidPdfReader -ArgumentList $argList -PassThru -WindowStyle Hidden
                                $proc.WaitForExit(10000)
                            } else {
                                Write-ApiLog ">>> [列印] 嘗試 Shell PrintTo (未偵測到指定閱讀器)..."
                                $proc = Start-Process -FilePath $savePath -Verb PrintTo -ArgumentList """$pName""" -PassThru -WindowStyle Hidden
                                $proc.WaitForExit(10000)
                            }
                            $res.success = $true; $res.message = "PDF 已傳送至列印佇列"
                            Send-SysAdminNotify -content "API：PDF 上傳並發送至 [$pName] (雙面:$($null -ne $duplexReq))。" -title "遠端列印"
                        } catch {
                            $res.message = "列印失敗: $($_.Exception.Message)"
                            Write-ApiLog "!!! [列印錯誤] $($_.Exception.Message)"
                        }

                        if ($restoreDuplex) {
                            try {
                                Write-ApiLog ">>> [還原] 恢復雙面設定"
                                Set-PrintConfiguration -PrinterName $pName -DuplexingMode $oldDuplexMode -ErrorAction SilentlyContinue
                            } catch {}
                        }
                    } else { $res.message = "找不到指定的印表機: $pName" }
                } else { $res.message = "僅支援 POST 方法" }
            }
            elseif ($path -eq "/printer/status") {
                $pName = $request.QueryString["name"]
                $all = Get-PrinterStatusData
                $target = $null
                foreach($item in $all) { if($item.Name -eq $pName) { $target = $item; break } }
                if ($null -ne $target) { $res.data = $target; $res.success = $true }
                else { $res.message = "找不到指定的印表機" }
            }
            elseif ($path -eq "/printer/refresh") {
                $pName = $request.QueryString["name"]
                $pObj = Get-WmiObject -Class Win32_Printer | Where-Object { $_.Name -eq $pName }
                if ($null -ne $pObj) {
                    $pObj.Pause(); Start-Sleep -Milliseconds 500; $pObj.Resume()
                    $res.success = $true
                    Send-SysAdminNotify -content "API：印表機 [$pName] 手動重新整理成功。" -title "維護操作"
                } else { $res.message = "找不到指定的印表機" }
            }
            elseif ($path -eq "/printer/clear") {
                $pName = $request.QueryString["name"]
                $jobs = Get-WmiObject Win32_PrintJob | Where-Object { $_.Name -like "*$pName*" }
                if ($jobs) { foreach($j in $jobs){$j.Delete()} }
                $res.success = $true; Send-SysAdminNotify -content "[$pName] 手動清理完成。" -title "手動操作"
            }
            elseif ($path -eq "/service/restart-spooler") {
                try {
                    Restart-Service "Spooler" -Force
                    $res.success = $true
                    Send-SysAdminNotify -content "API：Spooler 服務已重啟。" -title "服務操作"
                } catch { Write-ApiLog "!!! [重啟失敗] $($_.Exception.Message)" }
            }
            elseif ($path -eq "/service/self-heal") {
                Invoke-SpoolerSelfHealing -reason "管理員遠端發動深度修復"; $res.success = $true
            }
            else { 
                $response.StatusCode = 404 
                Write-ApiLog "!!! [路徑錯誤] 無法辨識的路徑: $path"
            }
        }

        $buffer = [System.Text.Encoding]::UTF8.GetBytes((ConvertTo-SimpleJson $res))
        $response.ContentType = "application/json"; $response.OutputStream.Write($buffer, 0, $buffer.Length); $response.Close()
    } catch { 
        Write-ApiLog "!!! [系統錯誤] $($_.Exception.Message)"
        $contextTask = $null 
    }
}