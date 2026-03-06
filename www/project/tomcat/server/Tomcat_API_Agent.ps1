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
$notifyUrl          = "http://$notifyIp$notifyEndpoint"
$notifyChannels     = Get-EnvArray "NOTIFY_CHANNELS" @("val")

$enableNotifyHealthCheck  = Get-EnvBool "ENABLE_NOTIFY_HEALTH_CHECK" $true
$notifyTimeoutMs          = Get-EnvInt "NOTIFY_TIMEOUT_MS" 1000
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
        
        if ($null -eq $contextTask) { $contextTask = $listener.BeginGetContext($null, $null) }
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { continue }

        $context = $listener.EndGetContext($contextTask); $contextTask = $null
        $req = $context.Request; $res = $context.Response; $path = $req.Url.AbsolutePath.ToLower()
        
        $res.AddHeader("Access-Control-Allow-Origin", "*")
        $res.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $res.AddHeader("Access-Control-Allow-Headers", "*") 
        if ($req.HttpMethod -eq "OPTIONS") { $res.StatusCode = 200; $res.Close(); continue }

        $out = @{ "success"=$false; "message"=""; "data"=$null }
        $handledBinary = $false

        if ($req.Headers["X-API-KEY"] -ne $apiKey) {
            $res.StatusCode = 401; $out.message = "金鑰錯誤"
        } 
        elseif ($path -eq "/tomcat/status") {
            try {
                $svc = Get-Service | Where-Object { $_.Name -eq $tomcatServiceName -or $_.DisplayName -eq $tomcatServiceName } | Select-Object -First 1
                $sysOs = Get-WmiObject Win32_OperatingSystem
                $memTotal = [math]::Round($sysOs.TotalVisibleMemorySize * 1024)
                $memFree = [math]::Round($sysOs.FreePhysicalMemory * 1024)
                $cpu = (Get-WmiObject Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average
                $hasDumps = (Get-ChildItem -Path $tomcatDir -Filter "hs_err_pid*" -File -ErrorAction SilentlyContinue).Count -gt 0
                
                $out.data = @{ "Status"=$svc.Status.ToString(); "ServiceName"=$svc.Name; "DisplayName"=$svc.DisplayName; "TomcatDir"=$tomcatDir; "SysCpu"=[math]::Round($cpu,1); "SysMemUsed"=($memTotal-$memFree); "SysMemTotal"=$memTotal; "SysMemPct"=[math]::Round((($memTotal-$memFree)/$memTotal)*100,1); "HasCrashDumps"=$hasDumps }
                $out.success = $true
            } catch { $out.message = "狀態讀取失敗" }
        }
        elseif ($path -eq "/tomcat/logs") {
            $logDate = Get-Date -Format "yyyy-MM-dd"
            $reqType = Get-Utf8QueryParam $req "type"
            $targetFile = $null
            if ($reqType -eq "stdout") {
                $f = Get-ChildItem -Path "$tomcatDir\logs" -Filter "*stdout*$logDate.log" | Sort-Object LastWriteTime -Descending
                if ($f) { $targetFile = $f[0].FullName }
            } elseif ($reqType -eq "stderr") {
                $f = Get-ChildItem -Path "$tomcatDir\logs" -Filter "*stderr*$logDate.log" | Sort-Object LastWriteTime -Descending
                if ($f) { $targetFile = $f[0].FullName }
            } else {
                $targetFile = Join-Path $tomcatDir "logs\catalina.$logDate.log"
                if (-not (Test-Path $targetFile)) { $targetFile = Join-Path $tomcatDir "logs\catalina.out" }
            }

            if ($targetFile -and (Test-Path $targetFile)) {
                $cnt = 100; $l = Get-Utf8QueryParam $req "lines"; if ($l -match "^\d+$") { $cnt = [int]$l }
                $out.data = Get-Content $targetFile -Tail $cnt -Encoding Default; $out.success = $true
            } else { $out.message = "找不到日誌" }
        }
        elseif ($path -eq "/tomcat/restart") {
            try {
                Restart-Service -Name $tomcatServiceName -Force -ErrorAction Stop
                $out.success = $true; $out.message = "Tomcat 重啟成功"
            } catch { $out.message = "重啟失敗: $($_.Exception.Message)" }
        }
        elseif ($path -eq "/tomcat/download-logs") {
            try {
                $zipName = "tomcat_logs_$(Get-Date -Format 'yyyyMMdd_HHmmss').zip"
                $zipPath = Join-Path $logPath $zipName
                Compress-Archive -Path "$tomcatDir\logs\*" -DestinationPath $zipPath -Force
                $bytes = [System.IO.File]::ReadAllBytes($zipPath)
                $res.ContentType = "application/zip"
                $res.AddHeader("Content-Disposition", "attachment; filename=`"$zipName`"")
                $res.OutputStream.Write($bytes, 0, $bytes.Length)
                $res.Close(); $handledBinary = $true
            } catch { $out.message = "備份日誌失敗" }
        }
        else { $res.StatusCode = 404 }

        if (-not $handledBinary) {
            $buf = [System.Text.Encoding]::UTF8.GetBytes((ConvertTo-SimpleJson $out))
            $res.ContentType = "application/json"; $res.OutputStream.Write($buf, 0, $buf.Length); $res.Close()
        }

        if ($restartScript) { Start-Sleep -Seconds 1; try { $listener.Abort() } catch {}; Start-Process powershell.exe -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""; [System.Environment]::Exit(0) }
    } catch { $contextTask = $null }
}