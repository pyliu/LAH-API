<#
.SYNOPSIS
    Tomcat Server 維運管理 HTTP API Agent
    
.DESCRIPTION
    本腳本為針對 Windows 環境下的 Tomcat 維運需求所開發的後端服務。
    透過核心 HttpListener 提供 RESTful API，實現對 Tomcat 服務的深度監控與自動化維護，
    包含服務狀態控制、程序佔用獵殺、資源使用率追蹤及自動日誌管理。

.HISTORY
    版本 v1.0
    - 實作基礎 HTTP 監聽器與 API 路由架構。
    - 支援 Tomcat 服務狀態查詢及標準 Windows 服務啟動、停止、重啟功能。

    版本 v1.5
    - 實作日誌讀取 API，支援 -Tail 高效檔案末端追蹤。
    - 整合第三方 PHP 通知介面，實現重大操作異地推送。

    版本 v2.0
    - 實作「殭屍程序獵殺 (Clear-ZombiePort)」，透過系統 Netstat 或 Get-NetTCPConnection 
      精準找出並強殺佔用連接埠的孤兒程序。
    - 實作資源監控系統，獲取全系統與特定 Tomcat PID 的 CPU & RAM 即時佔用數據。
    - 支援 JVM Crash Dumps (hs_err_pid) 系統目錄偵測與自動掃描。
    - 實作日誌打包 API，支援將 logs 目錄壓縮為二進位 ZIP 串流傳輸。

    版本 v2.1 - [目前版本]
    - 安全性強化：將預設監聽連接埠變更為 18888，並修改通知頻道為 "val"。
    - 檔案生命週期管理：優化 Cleanup-OldLogs，支援自動清理超過 7 天的歷史 ZIP 壓縮檔。
    - 修正 CORS 預檢邏輯，支援現代網路安全性標頭 (Access-Control-Allow-Headers: *)。
    - 強化偵錯日誌：記錄每次自動清理操作與程序獵殺事件。
    - 記憶體與網路優化：實作日誌分塊打包 (Staging) 與 Chunked Streaming，徹底防堵 OOM 與前端斷流錯誤。
    - 容錯機制：優化通知 Timeout 捕捉，防堵無窮重啟迴圈。
    - 推播整合優化：根據 PHP API 規範，改用 application/x-www-form-urlencoded 並補齊 type、sender 等必填欄位。

.USAGE_NOTES
    1. 權限：必須以「系統管理員 (Administrator)」身分執行，否則無法重啟服務或獵殺程序。
    2. 配置：系統會優先載入同目錄下的 Tomcat_API_Agent.env 環境變數檔案。
    3. 防火牆：啟動時會嘗試自動建立名為 "TomcatApiServer_Port_18888" 的入站規則。
    4. 認證：API 使用 Header 的 X-API-KEY 進行簡單權杖認證。

.PRECAUTIONS
    - 獵殺程序功能極具破壞性，啟動前請確保 Port 18888 僅供此 Agent 使用。
    - 日誌打包作業會耗費伺服器 IO，建議於低負載時段執行。
    - 定時清理功能會偵測檔案的 LastWriteTime，請勿隨意更動日誌目錄的時間戳。
#>

# -------------------------------------------------------------------------
# 1. 基礎設定區 (優先讀取 .env)
# -------------------------------------------------------------------------

$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } elseif ($MyInvocation.MyCommand.Path) { Split-Path $MyInvocation.MyCommand.Path } else { $PWD.Path }
# 精準定位並鎖定腳本的絕對路徑，確保重啟時不會找不到檔案
$global:AgentScriptPath = if ($PSCommandPath) { $PSCommandPath } elseif ($MyInvocation.MyCommand.Path) { $MyInvocation.MyCommand.Path } else { Join-Path $scriptDir "Tomcat_API_Agent.ps1" }

$envFile = Join-Path $scriptDir "Tomcat_API_Agent.env"
$envConfig = @{}

if (Test-Path $envFile) {
    try {
        foreach ($line in Get-Content $envFile -Encoding UTF8) {
            $line = $line.Trim()
            if ($line.StartsWith("#") -or $line -eq "") { continue }
            $parts = $line -split '=', 2
            if ($parts.Length -eq 2) {
                $key = $parts[0].Trim(); $val = $parts[1].Trim()
                if ($val -match '^"(.*)"$') { $val = $matches[1] } elseif ($val -match "^'(.*)'$") { $val = $matches[1] }
                $envConfig[$key] = $val
            }
        }
        Write-Host ">>> [系統] 已成功載入外部設定檔: Tomcat_API_Agent.env" -ForegroundColor Cyan
    } catch { 
        Write-Host "!!! [系統] 讀取設定檔失敗，降級使用內建預設值" -ForegroundColor Yellow 
    }
}

function Get-EnvString($key, $default) { if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return $envConfig[$key] } return $default }
function Get-EnvInt($key, $default) { if ($envConfig.Contains($key) -and $envConfig[$key] -match '^\d+$') { return [int]$envConfig[$key] } return $default }
function Get-EnvBool($key, $default) { if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return ($envConfig[$key] -match '^(true|1|yes)$') } return $default }
function Get-EnvArray($key, [string[]]$default) { if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return @($envConfig[$key] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }) } return $default }

# 變數定義
$port               = Get-EnvInt "PORT" 18888
$apiKey             = Get-EnvString "API_KEY" "TomcatSecretKey123!"      
$logPath            = Get-EnvString "LOG_PATH" "C:\Temp\TomcatApiLogs"
$maxLogSizeBytes    = Get-EnvInt "MAX_LOG_SIZE_BYTES" 10485760                        
$maxHistory         = Get-EnvInt "MAX_HISTORY" 5                          
$logRetentionDays   = Get-EnvInt "LOG_RETENTION_DAYS" 7                   

$tomcatServiceName  = Get-EnvString "TOMCAT_SERVICE_NAME" "Apache Tomcat 7.0 Tomcat7"
$tomcatDir          = Get-EnvString "TOMCAT_DIR" "C:\Tomcat 7.0"

$enableScheduledRestart = Get-EnvBool "ENABLE_SCHEDULED_RESTART" $true
$scheduledRestartCron   = Get-EnvString "SCHEDULED_RESTART_CRON" "30 7 * * *"

$notifyIp           = Get-EnvString "NOTIFY_IP" "220.1.34.75"
$notifyPort         = Get-EnvInt "NOTIFY_PORT" 80
$notifyEndpoint     = Get-EnvString "NOTIFY_ENDPOINT" "/api/notification_json_api.php"
$notifyChannels     = Get-EnvArray "NOTIFY_CHANNELS" @("val")

$enableNotifyHealthCheck  = Get-EnvBool "ENABLE_NOTIFY_HEALTH_CHECK" $true
$notifyTimeoutMs          = Get-EnvInt "NOTIFY_TIMEOUT_MS" 5000
$enableAdminNotifications = Get-EnvBool "ENABLE_ADMIN_NOTIFICATIONS" $true    

$global:IsNotifyServerOnline = $true 
$global:LastCronRunTime = $null 
$restartScript = $false
$restartComputer = $false

# -------------------------------------------------------------------------
# 2. 核心函數庫
# -------------------------------------------------------------------------

function ConvertTo-SimpleJson {
    param($InputObject)
    if ($null -eq $InputObject) { return "null" }
    if ($InputObject -is [string]) { return """$($InputObject.Replace('\', '\\').Replace('"', '\"').Replace("`n", "\n").Replace("`r", "\r").Replace("`t", "\t"))""" }
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
    try { foreach ($prop in $InputObject.PSObject.Properties) { $objPairs.Add("""$($prop.Name)"":" + (ConvertTo-SimpleJson $prop.Value)) } } catch { return """$($InputObject.ToString())""" }
    if ($objPairs.Count -gt 0) { return "{" + [string]::Join(",", $objPairs) + "}" } else { return """$($InputObject.ToString())""" }
}

function Write-ApiLog {
    param([string]$message, [ConsoleColor]$Color = "Gray")
    try {
        if (-not (Test-Path $logPath)) { [void](New-Item -ItemType Directory -Path $logPath -Force) }
        $today = Get-Date -Format "yyyy-MM-dd"
        $fullPath = Join-Path $logPath "TomcatApi_$today.log"
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
        
        if (Test-Path $fullPath) {
            $fileItem = Get-Item $fullPath -ErrorAction SilentlyContinue
            if ($null -ne $fileItem -and $fileItem.Length -ge $maxLogSizeBytes) {
                if (Test-Path "$fullPath.$maxHistory") { Remove-Item "$fullPath.$maxHistory" -Force }
                for ($i = $maxHistory - 1; $i -ge 1; $i--) { 
                    $src = "$fullPath.$i"; $dest = "$fullPath.$($i + 1)"
                    if (Test-Path $src) { Move-Item $src $dest -Force } 
                }
                Move-Item $fullPath "$fullPath.1" -Force
            }
        }
        Add-Content -Path $fullPath -Value $logEntry
        Write-Host $logEntry -ForegroundColor $Color
    } catch {}
}

function Cleanup-OldLogs {
    try {
        if (Test-Path $logPath) {
            $limitDate = (Get-Date).AddDays(-$logRetentionDays)
            $oldFiles = Get-ChildItem -Path $logPath -File | Where-Object { 
                ($_.Name -like "TomcatApi_*.log*" -or $_.Name -like "tomcat_logs_*.zip") -and $_.LastWriteTime -lt $limitDate 
            }
            if ($oldFiles) { 
                foreach ($f in $oldFiles) { 
                    Remove-Item $f.FullName -Force 
                    Write-ApiLog ">>> [系統維護] 已自動清理過期檔案: $($f.Name)" -Color DarkGray
                } 
            }
        }
    } catch {
        Write-ApiLog "!!! 清理舊日誌發生錯誤: $($_.Exception.Message)" -Color Yellow
    }
}

function Setup-FirewallRule {
    param([int]$targetPort)
    Write-ApiLog "檢查防火牆規則 Port $targetPort..." -Color Yellow
    try {
        $ruleName = "TomcatApiServer_Port_$targetPort"
        $check = netsh advfirewall firewall show rule name="$ruleName" 2>&1
        if ($check -match "No rules match" -or $check -match "找不到符合的") {
            netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=$targetPort
            Write-ApiLog "[系統初始化] 已建立防火牆規則: $ruleName" -Color Cyan
        }
    } catch {}
}

function Clear-ZombiePort {
    param([int]$TargetPort)
    try {
        $pidsToKill = @()
        if (Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue) {
            $conns = Get-NetTCPConnection -LocalPort $TargetPort -State Listen -ErrorAction SilentlyContinue
            if ($conns) { foreach ($c in $conns) { $pidsToKill += $c.OwningProcess } }
        }
        if ($pidsToKill.Count -eq 0) {
            $netstat = netstat -ano | Select-String "LISTENING" | Select-String ":$TargetPort\s+"
            if ($netstat) { foreach ($lineItem in $netstat) { $parts = $lineItem.Line.Trim() -split '\s+'; $zombiePid = $parts[-1]; if ($zombiePid -match "^\d+$") { $pidsToKill += [int]$zombiePid } } }
        }
        $pidsToKill = $pidsToKill | Select-Object -Unique
        foreach ($zPid in $pidsToKill) {
            if ($zPid -eq 4) { Write-ApiLog "!!! [致命警告] Port $TargetPort 被系統核心佔用。" -Color Red }
            elseif ($zPid -ne $PID -and $zPid -gt 4) {
                $procName = "Unknown"
                try { $procName = (Get-Process -Id $zPid -ErrorAction SilentlyContinue).ProcessName } catch {}
                Write-ApiLog ">>> 獵殺 Port $TargetPort 佔用程序: [$procName] (PID: $zPid)" -Color Yellow
                cmd.exe /c "taskkill /F /PID $zPid /T" 2>&1 | Out-Null
                Stop-Process -Id $zPid -Force -ErrorAction SilentlyContinue
                Start-Sleep -Seconds 2
            }
        }
    } catch { Write-ApiLog "!!! 清除佔用異常: $($_.Exception.Message)" }
}

function Send-SysAdminNotify {
    param(
        [string]$title, 
        [string]$content
    )
    
    if (-not $enableAdminNotifications) { return }
    $timeout = if ($notifyTimeoutMs -lt 3000) { 3000 } else { $notifyTimeoutMs }

    try {
        $url = "http://${notifyIp}:${notifyPort}${notifyEndpoint}"
        
        # ?? 取得伺服器本機 IPv4 位址
        $ipList = [System.Net.Dns]::GetHostAddresses($env:COMPUTERNAME) | Where-Object { $_.AddressFamily -eq 'InterNetwork' }
        $serverIp = if ($ipList) { $ipList[0].ToString() } else { "127.0.0.1" }
        $senderName = "$env:COMPUTERNAME ($serverIp)"
        
        # ?? 根據 PHP API 要求，建立傳統的 URL-Encoded 表單參數陣列
        # 將 sender 改為伺服器的電腦名稱加上 IP ($senderName)
        $postParams = @(
            "type=add_notification",
            "title=$([System.Uri]::EscapeDataString($title))",
            "content=$([System.Uri]::EscapeDataString($content))",
            "sender=$([System.Uri]::EscapeDataString($senderName))",
            "priority=1",
            "from_ip=$serverIp"
        )
        
        # 處理陣列格式 (PHP 中的 $_POST['channels'] 預期格式為 channels[]=val1&channels[]=val2)
        if ($null -ne $notifyChannels) {
            foreach ($c in $notifyChannels) {
                $postParams += "channels[]=$([System.Uri]::EscapeDataString($c))"
            }
        }
        
        # 將陣列組合成 Query String
        $postString = $postParams -join "&"
        
        Write-ApiLog " -> [通知系統] 準備發送請求至 $url" -Color DarkGray
        
        $req = [System.Net.WebRequest]::Create($url)
        $req.Method = "POST"
        # ?? 關鍵：必須使用 application/x-www-form-urlencoded，PHP 的 $_POST 才能成功接收
        $req.ContentType = "application/x-www-form-urlencoded; charset=utf-8"
        $req.Timeout = $timeout
        
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($postString)
        $req.ContentLength = $bytes.Length
        $stream = $req.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        
        # 接收 PHP 伺服器的回應
        $res = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($res.GetResponseStream())
        $resBody = $reader.ReadToEnd()
        $reader.Close()
        $res.Close()
        
        # ?? 將 PHP 回傳的 Unicode 編碼 (如 \u65b0\u589e) 解碼為人類可讀的中文字
        try { $resBody = [System.Text.RegularExpressions.Regex]::Unescape($resBody) } catch {}
        
        # ?? 印出 PHP 的 JSON 回應，讓成功與否一目了然
        Write-ApiLog " -> [通知系統] PHP 伺服器回應: $resBody" -Color Green
        
    } catch {
        $errMsg = $_.Exception.Message
        if ($null -ne $_.Exception.InnerException) { 
            $errMsg = $_.Exception.InnerException.Message 
        }
        
        # 如果 PHP 伺服器回傳 400/500 等錯誤代碼，嘗試讀取它吐出的錯誤內容
        if ($_.Exception -is [System.Net.WebException] -and $null -ne $_.Exception.Response) {
            try {
                $errReader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $errBody = $errReader.ReadToEnd()
                $errReader.Close()
                
                # ?? 錯誤內容也一併解碼中文
                try { $errBody = [System.Text.RegularExpressions.Regex]::Unescape($errBody) } catch {}
                
                $errMsg += " | PHP 回傳內容: $errBody"
            } catch {}
        }
        
        if ($errMsg -match "要求已經中止" -or $errMsg -match "canceled" -or $errMsg -match "逾時") {
            $errMsg = "連線逾時 (通知伺服器未在 $($timeout/1000) 秒內回應)"
        } elseif ($errMsg -match "無法連接到遠端伺服器" -or $errMsg -match "Unable to connect") {
            $errMsg = "無法連接到通知伺服器 (${notifyIp}:${notifyPort})"
        }
        
        Write-ApiLog "!!! [通知系統] 推播發送失敗: $errMsg" -Color DarkYellow
    }
}

function Execute-ScheduledMaintenance {
    Write-ApiLog ">>> [排程任務] 觸發定時維護 (清理快取與重啟服務)..." -Color Cyan
    try {
        Write-ApiLog " -> 正在停止 Tomcat 服務..."
        Stop-Service -Name $tomcatServiceName -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 5
        
        Write-ApiLog " -> 正在清理 work 與 temp 目錄..."
        $w = Join-Path $tomcatDir "work"; $t = Join-Path $tomcatDir "temp"
        if (Test-Path $w) { Get-ChildItem -Path $w -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue }
        if (Test-Path $t) { Get-ChildItem -Path $t -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue }
        
        Write-ApiLog " -> 正在啟動 Tomcat 服務..."
        Start-Service -Name $tomcatServiceName -ErrorAction Stop
        
        Write-ApiLog ">>> [排程任務] Tomcat 維護完成！" -Color Green
        
        if ($enableAdminNotifications) {
            Send-SysAdminNotify -title "排程維護通知" -content "Tomcat 伺服器已完成每日定時重啟與快取清理作業。"
        }
    } catch {
        Write-ApiLog "!!! [排程任務] 維護發生錯誤: $($_.Exception.Message)" -Color Red
        if ($enableAdminNotifications) {
            Send-SysAdminNotify -title "排程維護異常" -content "Tomcat 定時維護發生錯誤: $($_.Exception.Message)"
        }
    }
}

function Get-Utf8QueryParam { 
    param($request, $key)
    if ($request.Url.Query -match "[?&]$key=([^&]*)") { try { return [System.Uri]::UnescapeDataString($matches[1].Replace("+", "%20")) } catch { return $null } }
    return $null
}

# -------------------------------------------------------------------------
# 3. 主程序 (HttpListener)
# -------------------------------------------------------------------------
Write-ApiLog "----------------------------------------" -Color Cyan
Write-ApiLog " Tomcat API Agent v2.1 (Port: $port) " -Color Cyan
Write-ApiLog "----------------------------------------" -Color Cyan

if (Test-Path $envFile) {
    Write-ApiLog ">>> [系統] 已載入外部設定檔: Tomcat_API_Agent.env" -Color Cyan
} else {
    Write-ApiLog "!!! [系統] 未偵測到外部設定檔，使用內建預設值" -Color Yellow
}

Setup-FirewallRule $port
Clear-ZombiePort $port

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
try {
    $listener.Start()
    Write-ApiLog "API 服務已啟動，監聽 Port $port" -Color Green
} catch {
    Write-ApiLog "!!! 無法綁定 Port $port，程序終止。" -Color Red; [System.Environment]::Exit(1)
}

$nextCleanup = Get-Date; $contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        if ($now -ge $nextCleanup) { Cleanup-OldLogs; $nextCleanup = $now.AddHours(24) }
        
        # 檢查並觸發每日定時排程維護
        if ($enableScheduledRestart -and $scheduledRestartCron -match "^(\d+)\s+(\d+)") {
            $cronMin = [int]$matches[1]
            $cronHour = [int]$matches[2]
            if ($now.Hour -eq $cronHour -and $now.Minute -eq $cronMin) {
                if ($null -eq $global:LastCronRunTime -or $global:LastCronRunTime.Date -ne $now.Date) {
                    $global:LastCronRunTime = $now
                    Execute-ScheduledMaintenance
                }
            }
        }

        if ($null -eq $contextTask) { $contextTask = $listener.BeginGetContext($null, $null) }
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { continue }

        $context = $listener.EndGetContext($contextTask); $contextTask = $null
        $req = $context.Request; $res = $context.Response; $path = $req.Url.AbsolutePath.ToLower()
        
        # 排除高頻輪詢請求，避免 Log 洗版
        if ($path -notmatch "^/(tomcat/status|tomcat/logs|server/logs)") {
            Write-ApiLog ">>> [請求] $($req.RemoteEndPoint) $path"
        }
        
        $res.AddHeader("Access-Control-Allow-Origin", "*")
        $res.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $res.AddHeader("Access-Control-Allow-Headers", "*") 
        if ($req.HttpMethod -eq "OPTIONS") { $res.StatusCode = 200; $res.Close(); continue }

        $out = @{ "success"=$false; "message"=""; "data"=$null }
        $handledBinary = $false
        
        $restartScript = $false
        $restartComputer = $false

        if ($req.Headers["X-API-KEY"] -ne $apiKey) {
            $res.StatusCode = 401; $out.message = "金鑰錯誤"
        } 
        elseif ($path -eq "/tomcat/status") {
            try {
                $svc = Get-Service | Where-Object { $_.Name -eq $tomcatServiceName -or $_.DisplayName -eq $tomcatServiceName } | Select-Object -First 1
                if (-not $svc) { throw "找不到服務: $tomcatServiceName" }

                $sysCpu = 0; $sysMemTotal = 0; $sysMemUsed = 0; $sysMemPct = 0; $tomcatCpuPct = 0; $tomcatMemBytes = 0
                
                $hasCrashDumps = $false
                try {
                    $dumps = Get-ChildItem -Path $tomcatDir -Filter "hs_err_pid*" -File -ErrorAction SilentlyContinue
                    if ($dumps -and $dumps.Count -gt 0) { $hasCrashDumps = $true }
                } catch {}

                try {
                    $sysOs = Get-WmiObject Win32_OperatingSystem
                    $sysMemTotal = [math]::Round($sysOs.TotalVisibleMemorySize * 1024)
                    $sysMemFree = [math]::Round($sysOs.FreePhysicalMemory * 1024)
                    $sysMemUsed = $sysMemTotal - $sysMemFree
                    if ($sysMemTotal -gt 0) { $sysMemPct = [math]::Round(($sysMemUsed / $sysMemTotal) * 100, 1) }
                    
                    $sysProcessor = Get-WmiObject Win32_Processor | Measure-Object -Property LoadPercentage -Average
                    $sysCpu = [math]::Round($sysProcessor.Average, 1)

                    if ($svc.Status -eq 'Running') {
                        $svcWmi = Get-WmiObject Win32_Service -Filter "Name='$($svc.Name)'"
                        if ($svcWmi -and $svcWmi.ProcessId -gt 0) {
                            $tProc = Get-Process -Id $svcWmi.ProcessId -ErrorAction SilentlyContinue
                            if ($tProc) {
                                $tomcatMemBytes = $tProc.WorkingSet64
                                $perf = Get-WmiObject Win32_PerfFormattedData_PerfProc_Process -Filter "IDProcess=$($svcWmi.ProcessId)" -ErrorAction SilentlyContinue
                                if ($perf) {
                                    $processorCount = $env:NUMBER_OF_PROCESSORS
                                    if (-not $processorCount -or $processorCount -eq 0) { $processorCount = 1 }
                                    $tomcatCpuPct = [math]::Round(($perf.PercentProcessorTime / $processorCount), 1)
                                }
                            }
                        }
                    }
                } catch { Write-ApiLog "獲取效能數據失敗: $($_.Exception.Message)" -Color Yellow }

                $out.data = @{ "ServiceName"=$svc.Name; "DisplayName"=$svc.DisplayName; "Status"=$svc.Status.ToString(); "TomcatDir"=$tomcatDir; "SysCpu"=$sysCpu; "SysMemTotal"=$sysMemTotal; "SysMemUsed"=$sysMemUsed; "SysMemPct"=$sysMemPct; "TomcatCpu"=$tomcatCpuPct; "TomcatMemUsed"=$tomcatMemBytes; "HasCrashDumps"=$hasCrashDumps }
                $out.success = $true; $out.message = "OK"
            } catch { 
                $out.message = "狀態讀取失敗: $($_.Exception.Message)" 
                Write-ApiLog "!!! 狀態讀取失敗: $($_.Exception.Message)" -Color Yellow
            }
        }
        elseif ($path -eq "/tomcat/logs") {
            $logDate = Get-Date -Format "yyyy-MM-dd"
            $reqType = Get-Utf8QueryParam $req "type"
            $targetFile = $null
            $searchPattern = ""

            if ($reqType -eq "stdout") { $searchPattern = "*stdout*" }
            elseif ($reqType -eq "stderr") { $searchPattern = "*stderr*" }
            else { $searchPattern = "catalina*" }

            # 先找今天的日誌
            $f = Get-ChildItem -Path "$tomcatDir\logs" -Filter "$searchPattern$logDate.log" -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending
            if ($f) { 
                $targetFile = $f[0].FullName 
            } else {
                # 若今天沒有，找最新那份
                $f = Get-ChildItem -Path "$tomcatDir\logs" -Filter "$searchPattern*.log" -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending
                if ($f) { $targetFile = $f[0].FullName }
            }

            if (-not $targetFile -and $reqType -ne "stdout" -and $reqType -ne "stderr") {
                if (Test-Path "$tomcatDir\logs\catalina.out") { $targetFile = Join-Path $tomcatDir "logs\catalina.out" }
            }

            if ($targetFile -and (Test-Path $targetFile)) {
                $cnt = 100; $l = Get-Utf8QueryParam $req "lines"; if ($l -match "^\d+$") { $cnt = [int]$l }
                try {
                    $logData = Get-Content $targetFile -Tail $cnt -Encoding Default -ErrorAction Stop
                    
                    if ($targetFile -notmatch $logDate -and $targetFile -notmatch "catalina\.out") {
                        $leaf = Split-Path $targetFile -Leaf
                        $logData = @("[系統提示] Tomcat 尚未產生今日 ($logDate) 的日誌。") + @(">>> 目前為您顯示最新的一份歷史日誌: $leaf") + @("---------------------------------------------------------") + $logData
                    }
                    
                    $out.data = $logData
                    $out.success = $true
                } catch { 
                    $out.message = "讀取檔案失敗: $($_.Exception.Message)" 
                    Write-ApiLog "!!! 讀取日誌失敗 ($reqType): $($_.Exception.Message)" -Color Yellow
                }
            } else { 
                $out.message = "伺服器找不到 [$reqType] 相關的日誌檔案" 
                Write-ApiLog "!!! 找不到日誌 ($reqType): 目錄內無匹配檔案" -Color Yellow
            }
        }
        elseif ($path -eq "/tomcat/restart") {
            try {
                Write-ApiLog ">>> 收到 API 請求：準備重啟 Tomcat 服務..." -Color Cyan
                Restart-Service -Name $tomcatServiceName -Force -ErrorAction Stop
                $out.success = $true; $out.message = "Tomcat 重啟成功"
                Write-ApiLog ">>> Tomcat 服務重啟成功" -Color Green
                if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作通知" -content "管理員已透過 API 成功重啟 Tomcat 服務。" }
            } catch { 
                $out.message = "重啟失敗: $($_.Exception.Message)" 
                Write-ApiLog "!!! Tomcat 服務重啟失敗: $($_.Exception.Message)" -Color Red
                if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作異常" -content "管理員嘗試透過 API 重啟 Tomcat 服務失敗: $($_.Exception.Message)" }
            }
        }
        elseif ($path -eq "/tomcat/clean-cache") {
            try {
                Write-ApiLog ">>> 收到 API 請求：準備清理 Tomcat 快取..." -Color Cyan
                $w = Join-Path $tomcatDir "work"; $t = Join-Path $tomcatDir "temp"
                if (Test-Path $w) { Get-ChildItem -Path $w -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue }
                if (Test-Path $t) { Get-ChildItem -Path $t -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue }
                $out.success = $true; $out.message = "快取目錄 (work/temp) 已清空"
                Write-ApiLog ">>> 已執行 Tomcat 快取清理" -Color Green
                if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作通知" -content "管理員已透過 API 成功清空 Tomcat 快取 (work/temp)。" }
            } catch { 
                $out.message = "清理失敗: $($_.Exception.Message)" 
                Write-ApiLog "!!! Tomcat 快取清理失敗: $($_.Exception.Message)" -Color Red
                if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作異常" -content "管理員嘗試透過 API 清空 Tomcat 快取失敗: $($_.Exception.Message)" }
            }
        } 
        elseif ($path -eq "/tomcat/clean-crash-dumps") {
            try {
                Write-ApiLog ">>> 收到 API 請求：準備清理 JVM Crash Dumps..." -Color Cyan
                $dumps = Get-ChildItem -Path $tomcatDir -Filter "hs_err_pid*" -File
                if ($dumps) {
                    $sz = 0; foreach ($f in $dumps) { $sz += $f.Length; Remove-Item $f.FullName -Force }
                    $out.success = $true; $out.message = "已刪除 $($dumps.Count) 個 Dump 檔，釋放 $([math]::Round($sz/1MB,2)) MB 空間"
                    Write-ApiLog ">>> 清理了 $($dumps.Count) 個 JVM Crash Dumps" -Color Green
                    if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作通知" -content "管理員已透過 API 成功清理 JVM 崩潰傾印檔，共釋放 $([math]::Round($sz/1MB,2)) MB 空間。" }
                } else { 
                    $out.success = $true; $out.message = "未發現 Dump 檔案" 
                }
            } catch { 
                $out.message = "刪除失敗: $($_.Exception.Message)" 
                Write-ApiLog "!!! JVM 崩潰檔清理失敗: $($_.Exception.Message)" -Color Red
                if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作異常" -content "管理員嘗試透過 API 清理 JVM 崩潰傾印檔失敗: $($_.Exception.Message)" }
            }
        }
        elseif ($path -eq "/tomcat/download-logs") {
            try {
                Write-ApiLog ">>> 收到 API 請求：準備打包下載日誌..." -Color Cyan
                if (-not (Test-Path $logPath)) { New-Item -ItemType Directory -Path $logPath -Force | Out-Null }
                
                $zipName = "tomcat_logs_$(Get-Date -Format 'yyyyMMdd_HHmmss').zip"
                $zipPath = Join-Path $logPath $zipName
                
                $stagingDir = Join-Path $logPath "staging_$([guid]::NewGuid().ToString().Substring(0,8))"
                New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null
                
                Write-ApiLog " -> 正在複製日誌至暫存區..."
                Copy-Item -Path "$tomcatDir\logs\*" -Destination $stagingDir -Recurse -Force -ErrorAction SilentlyContinue
                
                $filesToZip = Get-ChildItem -Path $stagingDir -File -Recurse
                if ($filesToZip.Count -gt 0) {
                    Write-ApiLog " -> 正在壓縮 $($filesToZip.Count) 個檔案..."
                    Compress-Archive -Path "$stagingDir\*" -DestinationPath $zipPath -Force
                    
                    $fileInfo = New-Object System.IO.FileInfo($zipPath)
                    $res.ContentType = "application/zip"
                    $res.AddHeader("Content-Disposition", "attachment; filename=`"$zipName`"")
                    $res.ContentLength64 = $fileInfo.Length
                    
                    Write-ApiLog " -> 正在傳輸檔案 ($zipName, $([math]::Round($fileInfo.Length/1MB, 2)) MB)..."
                    
                    $fileStream = [System.IO.File]::OpenRead($zipPath)
                    $buffer = New-Object byte[] 65536
                    try {
                        while (($read = $fileStream.Read($buffer, 0, $buffer.Length)) -gt 0) {
                            $res.OutputStream.Write($buffer, 0, $read)
                        }
                        $handledBinary = $true
                        Write-ApiLog ">>> 日誌打包下載完成！" -Color Green
                    } catch {
                        Write-ApiLog "!!! 檔案傳輸中斷 (前端逾時或取消): $($_.Exception.Message)" -Color Yellow
                        $handledBinary = $true 
                    } finally {
                        $fileStream.Close()
                        try { $res.Close() } catch {}
                    }
                } else {
                    throw "無法複製任何日誌檔案，檔案可能被完全鎖定或目錄為空。"
                }
                
                Remove-Item -Path $stagingDir -Recurse -Force -ErrorAction SilentlyContinue
                
            } catch { 
                $res.StatusCode = 500
                $out.message = "備份日誌失敗: $($_.Exception.Message)" 
                Write-ApiLog "!!! 打包日誌失敗: $($_.Exception.Message)" -Color Red
                
                if ($null -ne $stagingDir -and (Test-Path $stagingDir)) { 
                    Remove-Item -Path $stagingDir -Recurse -Force -ErrorAction SilentlyContinue 
                }
            }
        }
        elseif ($path -eq "/server/logs") {
            $logDate = Get-Date -Format "yyyy-MM-dd"
            $targetFile = Join-Path $logPath "TomcatApi_$logDate.log"
            
            if (-not (Test-Path $targetFile)) {
                $f = Get-ChildItem -Path $logPath -Filter "TomcatApi_*.log" -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending
                if ($f) { $targetFile = $f[0].FullName }
            }

            if ($targetFile -and (Test-Path $targetFile)) {
                $cnt = 100; $l = Get-Utf8QueryParam $req "lines"; if ($l -match "^\d+$") { $cnt = [int]$l }
                try {
                    $logData = Get-Content $targetFile -Tail $cnt -Encoding Default -ErrorAction Stop
                    if ($targetFile -notmatch $logDate) {
                        $leaf = Split-Path $targetFile -Leaf
                        $logData = @("[系統提示] Agent 尚未產生今日 ($logDate) 的日誌。") + @(">>> 目前為您顯示最新的一份歷史日誌: $leaf") + @("---------------------------------------------------------") + $logData
                    }
                    $out.data = $logData
                    $out.success = $true
                } catch { 
                    $out.message = "讀取錯誤: $($_.Exception.Message)" 
                    Write-ApiLog "!!! 讀取 Agent 日誌失敗: $($_.Exception.Message)" -Color Yellow
                }
            } else { 
                $out.message = "找不到今日的 Agent 日誌檔案" 
                Write-ApiLog "!!! 找不到 Agent 日誌: 目錄內無匹配檔案" -Color Yellow
            }
        }
        elseif ($path -eq "/server/restart-script") {
            $out.success = $true; $out.message = "Agent 腳本即將重啟..."; $restartScript = $true
        }
        elseif ($path -eq "/server/restart-computer") {
            $out.success = $true; $out.message = "伺服器即將重新啟動..."; $restartComputer = $true
        }
        else { $res.StatusCode = 404 }

        if (-not $handledBinary) {
            $buf = [System.Text.Encoding]::UTF8.GetBytes((ConvertTo-SimpleJson $out))
            $res.ContentType = "application/json"; $res.OutputStream.Write($buf, 0, $buf.Length); $res.Close()
        }

        if ($restartScript) { 
            Write-ApiLog ">>> 收到 API 指令：Agent 腳本即將重啟..." -Color Yellow
            try {
                if ($enableAdminNotifications) { Send-SysAdminNotify -title "系統操作通知" -content "管理員已透過 API 觸發 Agent 腳本重啟作業。" }
                Start-Sleep -Seconds 1
                try { $listener.Abort() } catch {}
                
                if (Test-Path $global:AgentScriptPath) {
                    $psArgs = @("-NoProfile", "-ExecutionPolicy", "Bypass", "-File", $global:AgentScriptPath)
                    Start-Process powershell.exe -ArgumentList $psArgs
                } else {
                    Write-ApiLog "!!! 找不到腳本路徑 ($global:AgentScriptPath)，無法啟動新程序！" -Color Red
                }
                
                [System.Environment]::Exit(0) 
            } catch {
                Write-ApiLog "!!! 執行重啟腳本失敗: $($_.Exception.Message)" -Color Red
            }
        }
        
        if ($restartComputer) {
            Write-ApiLog ">>> 伺服器即將關機重啟..." -Color Red
            try { 
                try { $listener.Abort() } catch {}
                if ($enableAdminNotifications) { Send-SysAdminNotify -content "API：收到管理員指令，伺服器即將在 5 秒後重新啟動。" -title "系統操作" }
                Start-Process "shutdown.exe" -ArgumentList "/r /t 5 /f /d p:4:1"
                [System.Environment]::Exit(0)
            } catch {
                Write-ApiLog "!!! 執行重啟伺服器失敗: $($_.Exception.Message)" -Color Red
            }
        }
    } catch { $contextTask = $null }
}