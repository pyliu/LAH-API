<#
.SYNOPSIS
    資深系統整合工程師實作版本 - Print Server HTTP API & Proactive Monitor
    版本：v17.52 (註解完整回補與環境變數整合版)
    
    修正紀錄：
    1. [註解修復] 重新補回遺失的系統架構說明、五大自癒機制與資安合規 (Zero-Ping) 文件。
    2. [環境變數] 完美保留 `.env` 外部設定檔讀取機制、型別防呆轉換。
    3. [路徑認證] 保留 UNC 網路芳鄰預先認證 (net use) 及虛擬路徑轉換 (Resolve-VirtualPath)。
    4. [功能整合] 整合 `/server/applyforms` API (地政表單) 及 `/printers` 的 `isLandSystem` 判斷旗標。
    5. [穩定性] 修正 `Get-PrintLogs` 在找不到 C:\printlog 時安靜回傳空陣列，防止伺服器報錯。
    6. [預覽支援] 支援二進位 PDF 串流回傳，優化 HTTP 回應處理邏輯。

.DESCRIPTION
    本腳本具備多層次自我檢查與自動修復機制 (Self-Healing & Resilience)：

    1. [列印作業自癒] 清除殭屍作業
       - 觸發機制：每次巡檢 (每分鐘執行一次)
       - 判斷條件：作業狀態為 Error (錯誤) 或 Deleting (刪除中但卡住)
       - 執行動作：自動呼叫 WMI Delete() 刪除該作業，防止單一壞檔卡住整台印表機佇列。

    2. [列印服務自癒] 偵測堵塞並重啟服務 (被動修復)
       - 觸發機制：佇列監控
       - 判斷條件：
         (1) 單台印表機佇列數超過 $queueThreshold (預設 20)
         (2) 且該數量與上次檢查時相同 (代表完全沒有消化)
         (3) 且發生堵塞的印表機數量超過 $maxStuckPrinters (預設 3)
       - 執行動作：停止 Spooler 服務 -> 清空 PRINTERS 暫存區 -> 重新啟動 Spooler 並發送通知。

    3. [系統健康維護] 排程環境重置與日誌輪替 (主動預防)
       - 觸發機制：Cron 排程表達式 ($scheduledHealCron，預設每天 07:30)
       - 執行動作：執行完整的服務重啟、暫存檔清理，並自動備份 C:\printlog 至 C:\Temp，保留 7 天備份。

    4. [腳本自身韌性] (Script Resilience)
       - 具備啟動重試機制 (Port 8888 衝突自動等待)、防火牆規則自動開通、以及通知防卡死 (TCP 探測) 功能。

    5. [資安合規與 SOC 友善設計 (Zero-Ping)]
       - 為了符合資安 SOC 監控要求，移除所有 ICMP (Ping) 操作，改用 TCP Port 9100/80 探測，避免被偵測為掃描攻擊。

.NOTES
    ?? 測試指令範例 (CMD):
    curl -H "X-API-KEY: %API_KEY%" http://localhost:8888/printers
#>

# -------------------------------------------------------------------------
# 1. 基礎設定區 (由 Printer_API_Agent.env 外部載入，並具備防呆預設值)
# -------------------------------------------------------------------------

$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } elseif ($MyInvocation.MyCommand.Path) { Split-Path $MyInvocation.MyCommand.Path } else { $PWD.Path }
$envFile = Join-Path $scriptDir "Printer_API_Agent.env"
$envConfig = @{}

# 嘗試讀取 .env 檔案
if (Test-Path $envFile) {
    try {
        foreach ($line in Get-Content $envFile -Encoding UTF8) {
            $line = $line.Trim()
            if ($line.StartsWith("#") -or $line -eq "") { continue }
            $parts = $line -split '=', 2
            if ($parts.Length -eq 2) {
                $key = $parts[0].Trim()
                $val = $parts[1].Trim()
                # 脫除字串雙引號與單引號
                if ($val -match '^"(.*)"$') { $val = $matches[1] }
                elseif ($val -match "^'(.*)'$") { $val = $matches[1] }
                $envConfig[$key] = $val
            }
        }
        Write-Host ">>> [System] 已成功載入外部設定檔: Printer_API_Agent.env" -ForegroundColor Cyan
    } catch {
        Write-Host "!!! [System] 讀取 Printer_API_Agent.env 失敗，將降級使用系統預設值" -ForegroundColor Yellow
    }
} else {
    Write-Host "--- [System] 未找到 Printer_API_Agent.env 檔案，將使用系統內建預設設定 ---" -ForegroundColor DarkGray
}

# 智慧型別轉換輔助函數
function Get-EnvString($key, $default) { if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return $envConfig[$key] } return $default }
function Get-EnvInt($key, $default) { if ($envConfig.Contains($key) -and $envConfig[$key] -match '^\d+$') { return [int]$envConfig[$key] } return $default }
function Get-EnvBool($key, $default) { if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return ($envConfig[$key] -match '^(true|1|yes)$') } return $default }
function Get-EnvArray($key, [string[]]$default) { if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return @($envConfig[$key] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }) } return $default }

# ----------------- 變數映射映射映射 -----------------
$port               = Get-EnvInt "PORT" 8888
$apiKey             = Get-EnvString "API_KEY" "YourSecretApiKey123"      
$logPath            = Get-EnvString "LOG_PATH" "C:\Temp"
$uploadPath         = Get-EnvString "UPLOAD_PATH" "C:\Temp\Uploads"           
$maxLogSizeBytes    = Get-EnvInt "MAX_LOG_SIZE_BYTES" 10485760 # 預設 10MB                       
$maxHistory         = Get-EnvInt "MAX_HISTORY" 5                          
$logRetentionDays   = Get-EnvInt "LOG_RETENTION_DAYS" 7                   

$printLogFilePath   = Get-EnvString "PRINT_LOG_FILE_PATH" "C:\printlog"

$defaultPdfReaders  = @(
    "C:\Program Files (x86)\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe",
    "C:\Program Files\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe",
    "C:\FoxitReader\Foxit Reader.exe",
    "C:\Program Files\Adobe\Acrobat DC\Acrobat\Acrobat.exe",
    "C:\Program Files (x86)\Adobe\Acrobat Reader DC\Reader\AcroRd32.exe"
)
$pdfReaderPaths     = Get-EnvArray "PDF_READER_PATHS" $defaultPdfReaders

$notifyIp           = Get-EnvString "NOTIFY_IP" "220.1.34.75"
$notifyPort         = Get-EnvInt "NOTIFY_PORT" 80
$notifyEndpoint     = Get-EnvString "NOTIFY_ENDPOINT" "/api/notification_json_api.php"
$notifyUrl          = "http://$notifyIp$notifyEndpoint"
$notifyChannels     = Get-EnvArray "NOTIFY_CHANNELS" @("HA10013859")

$enableNotifyHealthCheck  = Get-EnvBool "ENABLE_NOTIFY_HEALTH_CHECK" $true
$notifyTimeoutMs          = Get-EnvInt "NOTIFY_TIMEOUT_MS" 1000
$enableAdminNotifications = Get-EnvBool "ENABLE_ADMIN_NOTIFICATIONS" $false    

$checkIntervalSec   = Get-EnvInt "CHECK_INTERVAL_SEC" 60                  
$errorThreshold     = Get-EnvInt "ERROR_THRESHOLD" 5                   
$monitorStartHour   = Get-EnvInt "MONITOR_START_HOUR" 8
$monitorEndHour     = Get-EnvInt "MONITOR_END_HOUR" 17
$monitorDays        = Get-EnvArray "MONITOR_DAYS" @("Monday", "Tuesday", "Wednesday", "Thursday", "Friday")

$enableAutoCleanup  = Get-EnvBool "ENABLE_AUTO_CLEANUP" $true               
$zombieTimeMinutes  = Get-EnvInt "ZOMBIE_TIME_MINUTES" 10                  
$enableAutoHeal     = Get-EnvBool "ENABLE_AUTO_HEAL" $true               
$maxStuckPrinters   = Get-EnvInt "MAX_STUCK_PRINTERS" 3                   
$enableScheduledHeal= Get-EnvBool "ENABLE_SCHEDULED_HEAL" $true
$scheduledHealCron  = Get-EnvString "SCHEDULED_HEAL_CRON" "30 7 * * *"       
$queueThreshold     = Get-EnvInt "QUEUE_THRESHOLD" 20                  
$queueStuckLimit    = Get-EnvInt "QUEUE_STUCK_LIMIT" 5                   

$excludeKeywords    = Get-EnvArray "EXCLUDE_KEYWORDS" @("PDF", "XPS", "Fax", "OneNote", "Microsoft Shared Fax")
$manualExcludePrinters = Get-EnvArray "MANUAL_EXCLUDE_PRINTERS" @("範例印表機名稱_A", "範例印表機名稱_B")

$enableDriveMapping = Get-EnvBool "ENABLE_DRIVE_MAPPING" $true
$mappedDriveLetter  = Get-EnvString "MAPPED_DRIVE_LETTER" "Z:"
$mappedDriveUncPath = Get-EnvString "MAPPED_DRIVE_UNC_PATH" "\\220.1.34.43\land_adm_web_ha\cer"
$networkUsername    = Get-EnvString "NETWORK_USERNAME" ""
$networkPassword    = Get-EnvString "NETWORK_PASSWORD" ""

# 全局狀態變數
$global:PrinterStateCache   = New-Object System.Collections.Hashtable
$global:PrinterErrorCount   = New-Object System.Collections.Hashtable 
$global:ExcludedPrinters    = New-Object System.Collections.Hashtable 
$global:QueueStuckCount     = New-Object System.Collections.Hashtable 
$global:LastQueueCount      = New-Object System.Collections.Hashtable 
$global:IsFirstRun          = $true
$global:ValidPdfReader      = $null
$global:LastCronRunTime     = $null 
$global:IsNotifyServerOnline = $true 
$restartScript = $false
$restartComputer = $false

# --- 列印紀錄快取變數 ---
$global:PrintLogCache = @()
$global:PrintLogLastWriteTime = [DateTime]::MinValue

# -------------------------------------------------------------------------
# 2. 核心函數庫
# -------------------------------------------------------------------------

function ConvertTo-SimpleJson {
    param($InputObject)
    if ($null -eq $InputObject) { return "null" }
    
    if ($InputObject -is [string]) { 
        $escapedStr = $InputObject.Replace('\', '\\').Replace('"', '\"').Replace("`n", "\n").Replace("`r", "\r").Replace("`t", "\t")
        return """$escapedStr""" 
    }
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
    
    if ($objPairs.Count -gt 0) { return "{" + [string]::Join(",", $objPairs) + "}" } else { return """$($InputObject.ToString())""" }
}

function Write-ApiLog {
    param([string]$message, [ConsoleColor]$Color = "Gray")
    try {
        if (-not (Test-Path $logPath)) { [void](New-Item -ItemType Directory -Path $logPath -Force) }
        $today = Get-Date -Format "yyyy-MM-dd"
        $fullPath = Join-Path $logPath "PrintApi_$today.log"
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
        if (Test-Path $fullPath) {
            if ((Get-Item $fullPath).Length -ge $maxLogSizeBytes) {
                if (Test-Path "$fullPath.$maxHistory") { Remove-Item "$fullPath.$maxHistory" -Force }
                for ($i = $maxHistory - 1; $i -ge 1; $i--) { $src = "$fullPath.$i"; $dest = "$fullPath.$($i + 1)"; if (Test-Path $src) { Move-Item $src $dest -Force } }
                Move-Item $fullPath "$fullPath.1" -Force
            }
        }
        Add-Content -Path $fullPath -Value $logEntry
        if ($Color -ne "Gray") { Write-Host $logEntry -ForegroundColor $Color } else { Write-Host $logEntry }
    } catch {}
}

function Cleanup-OldLogs {
    try {
        if (Test-Path $logPath) {
            $limitDate = (Get-Date).AddDays(-$logRetentionDays)
            $oldFiles = Get-ChildItem -Path $logPath -Filter "PrintApi_*.log*" | Where-Object { $_.LastWriteTime -lt $limitDate }
            if ($null -ne $oldFiles) { foreach ($file in $oldFiles) { Remove-Item $file.FullName -Force } }
        }
    } catch {}
}

# --- 備份與清理 printlog 檔案函數 ---
function Rotate-PrintLog {
    if (-not (Test-Path $printLogFilePath -PathType Leaf)) { return }
    
    $backupName = "printlog.$((Get-Date).ToString('yyyyMMdd'))"
    $backupPath = Join-Path $logPath $backupName

    try {
        if (Test-Path $backupPath) { Remove-Item $backupPath -Force }
        Move-Item -Path $printLogFilePath -Destination $backupPath -Force
        New-Item -Path $printLogFilePath -ItemType File -Force | Out-Null
        
        Write-ApiLog ">>> [系統維護] 已備份並重置列印紀錄 -> $backupName" -Color Green
        
        $limitDate = (Get-Date).AddDays(-7)
        $oldLogs = Get-ChildItem -Path $logPath -Filter "printlog.*" | Where-Object { $_.LastWriteTime -lt $limitDate }
        if ($oldLogs) {
            foreach ($file in $oldLogs) {
                Remove-Item $file.FullName -Force
                Write-ApiLog ">>> [系統維護] 已刪除過期備份檔 -> $($file.Name)" -Color DarkGray
            }
        }
        
        $global:PrintLogCache = @()
        $global:PrintLogLastWriteTime = [DateTime]::MinValue
    } catch {
        Write-ApiLog "!!! [系統維護] 備份 printlog 失敗: $($_.Exception.Message)" -Color Red
    }
}

function Setup-FirewallRule {
    param([int]$targetPort)
    Write-ApiLog "正在檢查防火牆規則 Port $targetPort..." -Color Yellow
    try {
        $ruleName = "PrintApiServer_Port_$targetPort"
        $check = netsh advfirewall firewall show rule name="$ruleName" 2>&1
        if ($check -match "No rules match") {
            Write-ApiLog ">>> 防火牆規則不存在，正在建立..." -Color Cyan
            netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=$targetPort
            Write-ApiLog "[系統初始化] 已自動建立防火牆規則: $ruleName"
        } else { Write-ApiLog ">>> 防火牆規則已存在。" -Color Green }
    } catch { Write-ApiLog "!!! [防火牆設定失敗] 請手動執行 netsh" -Color Red }
}

function Test-TcpConnection {
    param([string]$target, [int]$port, [int]$timeoutMs)
    $tcp = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $tcp.BeginConnect($target, $port, $null, $null)
        if ($async.AsyncWaitHandle.WaitOne($timeoutMs, $false)) { $tcp.EndConnect($async); return $true }
        return $false
    } catch { return $false } finally { if ($tcp.Connected) { $tcp.Close() } else { $tcp.Close() } }
}

function Send-SysAdminNotify {
    param([string]$content, [string]$title = "印表機系統通知")
    if ($null -eq $notifyChannels -or $notifyChannels.Count -eq 0) { return }
    if ($enableNotifyHealthCheck -and (-not $global:IsNotifyServerOnline)) { return }

    try {
        $localIp = "127.0.0.1"
        try { $ipConfig = Get-WmiObject Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled }; if ($null -ne $ipConfig) { if ($ipConfig -is [array]) { $localIp = $ipConfig[0].IPAddress[0] } else { $localIp = $ipConfig.IPAddress[0] } } } catch { }
        $fields = @{ "type"="add_notification"; "title"=$title; "content"=$content; "priority"="3"; "sender"="$($env:COMPUTERNAME) ($localIp)"; "from_ip"=$localIp }
        $encodedParts = New-Object System.Collections.Generic.List[string]
        foreach ($key in $fields.Keys) { $encodedParts.Add("$key=$([System.Uri]::EscapeDataString($fields[$key]))") }
        if ($null -ne $notifyChannels) { foreach ($chan in $notifyChannels) { $encodedParts.Add("channels[]=$([System.Uri]::EscapeDataString($chan))") } }
        $postBody = [string]::Join("&", $encodedParts)
        
        $req = [System.Net.WebRequest]::Create($notifyUrl); $req.Method = "POST"; $req.ContentType = "application/x-www-form-urlencoded"; $req.Timeout = 1000 
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($postBody); $req.ContentLength = $bytes.Length
        $reqStream = $req.GetRequestStream(); $reqStream.Write($bytes, 0, $bytes.Length); $reqStream.Close()
        $resp = $req.GetResponse(); $resp.Close()
    } catch { Write-ApiLog "!!! [通知失敗] $($_.Exception.Message)" }
}

function Invoke-SpoolerSelfHealing {
    param([string]$reason)
    Write-ApiLog "!!! [自癒啟動] $reason"
    Send-SysAdminNotify -title "?? 自癒啟動" -content "偵測到異常 ($reason)，正在執行修復。"
    try {
        Stop-Service "Spooler" -Force; Start-Sleep -Seconds 3
        if (Test-Path "C:\Windows\System32\spool\PRINTERS") { Get-ChildItem -Path "C:\Windows\System32\spool\PRINTERS\*" -Include *.* -Force | Remove-Item -Force }
        Start-Service "Spooler"
        Send-SysAdminNotify -title "? 自癒完成" -content "服務已重啟。"
    } catch { Send-SysAdminNotify -title "? 自癒失敗" -content "錯誤: $($_.Exception.Message)" }
}

function Test-CronMatch {
    param($cron, $now)
    if ([string]::IsNullOrEmpty($cron)) { return $false }
    $parts = $cron.Split(" "); if ($parts.Count -ne 5) { return $false }
    $min=$parts[0]; $hour=$parts[1]; $dom=$parts[2]; $month=$parts[3]; $dow=$parts[4]
    function Check($p, $v) {
        if ($p -eq "*") { return $true }
        if ($p -match "^(\*|\d+)/(\d+)$") { return ($v % [int]$matches[2]) -eq 0 }
        if ($p -match ",") { foreach($i in $p.Split(",")){ if([int]$i -eq $v){return $true} }; return $false }
        return [int]$p -eq $v
    }
    return (Check $min $now.Minute) -and (Check $hour $now.Hour) -and (Check $dom $now.Day) -and (Check $month $now.Month) -and (Check $dow [int]$now.DayOfWeek)
}

function Get-Utf8QueryParam { 
    param($request, $key)
    $rawUrl = $request.RawUrl
    if ($rawUrl -match "[?&]$key=([^&]*)") {
        $encodedVal = $matches[1]
        $encodedVal = $encodedVal.Replace("+", "%20")
        try { return [System.Uri]::UnescapeDataString($encodedVal) } catch { return $null }
    }
    return $null
}

# --- 路徑轉換函數 (處理正斜線與 UNC 對應) ---
function Resolve-VirtualPath {
    param([string]$rawPath)
    if ([string]::IsNullOrEmpty($rawPath)) { return $null }
    
    # 1. 統一將正斜線轉換為 Windows 標準反斜線
    $path = $rawPath.Replace("/", "\")
    
    # 2. 如果啟用對應，且路徑以指定的磁碟機代號開頭 (不分大小寫)，則替換為 UNC 路徑
    if ($enableDriveMapping -and $path -match "^(?i)$([regex]::Escape($mappedDriveLetter))\\?(.*)$") {
        $path = Join-Path $mappedDriveUncPath $matches[1]
    }
    
    return $path
}

# --- 解析當日列印紀錄函數 (超級效能逐行讀取版) ---
function Get-PrintLogs {
    if (-not (Test-Path $printLogFilePath -PathType Leaf)) {
        return @() # 找不到紀錄檔時回傳空陣列，不再拋出例外錯誤
    }

    $currentFileInfo = Get-Item $printLogFilePath -ErrorAction Stop
    $currentWriteTime = $currentFileInfo.LastWriteTime

    if ($global:PrintLogCache -eq $null -or $global:PrintLogCache.Length -eq 0 -or $currentWriteTime -gt $global:PrintLogLastWriteTime) {
        Write-ApiLog ">>> [Cache] 紀錄檔已更新或無快取，開始重新解析..." -Color Cyan
        
        $results = New-Object System.Collections.ArrayList
        
        $enUS = New-Object System.Globalization.CultureInfo("en-US")
        $now = Get-Date
        $todayMonthStr  = $now.ToString("MMM", $enUS) # "Mar"
        $todayDay       = $now.Day                    # 3
        $todayYearStr   = $now.ToString("yyyy")       # "2026"
        $todayFormatted = $now.ToString("yyyy-MM-dd")

        $fs = $null
        $sr = $null

        try {
            $fs = New-Object System.IO.FileStream($printLogFilePath, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
            $sr = New-Object System.IO.StreamReader($fs, [System.Text.Encoding]::Default)
            
            while (-not $sr.EndOfStream) {
                $line = $sr.ReadLine()
                if ($line -match "([a-zA-Z]{3}\s+[a-zA-Z]{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}\s+.+?\d{4})") {
                    $timeStr = $matches[1]
                    if ($timeStr -match "$todayMonthStr\s+0?$todayDay\b" -and $timeStr -match "$todayYearStr$") {
                        $time = ""
                        if ($timeStr -match "(\d{2}:\d{2}:\d{2})") { $time = $matches[1] }
                        
                        if (-not $sr.EndOfStream) {
                            $dataLine = $sr.ReadLine()
                            if ($dataLine -match '(?i)"([^"]+\.pdf)"\s+"([^"]+)"') {
                                $obj = New-Object PSObject
                                $obj | Add-Member NoteProperty date $todayFormatted
                                $obj | Add-Member NoteProperty time $time
                                $obj | Add-Member NoteProperty path $matches[1]
                                $obj | Add-Member NoteProperty printer $matches[2]
                                [void]$results.Add($obj)
                            }
                        }
                    }
                }
            }
        } catch {
            Write-ApiLog "!!! [Error] 讀取 printlog 失敗: $($_.Exception.Message)" -Color Red
        } finally {
            if ($null -ne $sr) { $sr.Close(); $sr.Dispose() }
            if ($null -ne $fs) { $fs.Close(); $fs.Dispose() }
        }
        
        $global:PrintLogCache = $results.ToArray()
        $global:PrintLogLastWriteTime = $currentWriteTime
        Write-ApiLog ">>> [Cache] 解析完成，已快取 $($global:PrintLogCache.Length) 筆當日紀錄。" -Color Green
    }
    
    return $global:PrintLogCache
}

# --- 獲取所有印表機狀態 (包含附掛當日列印紀錄) ---
function Get-PrinterStatusData {
    $results = New-Object System.Collections.Generic.List[Object]
    $portMap = @{}
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

    $logsByPrinter = @{}
    try {
        if (Test-Path $printLogFilePath -PathType Leaf) {
            $allLogs = @(Get-PrintLogs)
            foreach ($log in $allLogs) {
                if (-not $logsByPrinter.ContainsKey($log.printer)) {
                    $logsByPrinter[$log.printer] = New-Object System.Collections.ArrayList
                }
                $item = New-Object PSObject
                $item | Add-Member NoteProperty date $log.date
                $item | Add-Member NoteProperty time $log.time
                $item | Add-Member NoteProperty path $log.path
                [void]$logsByPrinter[$log.printer].Add($item)
            }
        }
    } catch { }

    try { $tcpPorts = Get-WmiObject -Class Win32_TCPIPPrinterPort -ErrorAction SilentlyContinue; if($tcpPorts){ foreach($t in $tcpPorts){ if($t.Name){$portMap[$t.Name]=$t.HostAddress} } } } catch {}
    $wmiPrinters = Get-WmiObject -Class Win32_Printer

    foreach ($p in $wmiPrinters) {
        $pName = $p.Name; $shouldSkip = $false
        foreach ($kw in $excludeKeywords) { if ($pName -like "*$kw*") { $shouldSkip=$true; break } }
        if ($shouldSkip) { continue }
        foreach ($exName in $manualExcludePrinters) { if ($pName -eq $exName) { $shouldSkip=$true; break } }
        if ($shouldSkip) { continue }

        $errDetails = ""; $finalStatus = "Ready"
        if ($p.WorkOffline) { $finalStatus = "Offline" }
        elseif ($p.DetectedErrorState -ne 0) { 
            $finalStatus = "Error"
            switch ($p.DetectedErrorState) {
                1  { $errDetails = "1: 狀態不明" }
                2  { $errDetails = "2: 其他錯誤" }
                3  { $errDetails = "3: 無錯誤" }
                4  { $errDetails = "4: 缺紙" }
                5  { $errDetails = "5: 碳粉不足" }
                6  { $errDetails = "6: 缺碳粉" }
                7  { $errDetails = "7: 機門開啟" }
                8  { $errDetails = "8: 夾紙" }
                9  { $errDetails = "9: 離線" }
                10 { $errDetails = "10: 服務請求" }
                11 { $errDetails = "11: 輸出紙匣已滿" }
                default { $errDetails = "硬體偵測錯誤代碼: $($p.DetectedErrorState)" }
            }
        }
        else {
            switch ($p.PrinterStatus) {
                1 { $finalStatus = "Error"; $errDetails = "未知 - 驅動/SNMP異常" }
                2 { $finalStatus = "Error"; $errDetails = "其他錯誤" }
                4 { $finalStatus = "Printing" } 5 { $finalStatus = "Warmup" }
                default { $finalStatus = "Ready"; if($p.PrinterStatus -ne 3){$finalStatus="Warning"} }
            }
        }
        
        $pIP = if ($portMap.ContainsKey($p.PortName)) { $portMap[$p.PortName] } else { $p.PortName }
        if ($pIP -match "^\d+\.\d+\.\d+\.\d+$") {
            if (-not (Test-TcpConnection $pIP 9100 200)) {
                if ($finalStatus -eq "Offline") { $errDetails = "無回應 (TCP/9100)" }
                elseif ($finalStatus -like "Ready*") { $finalStatus = "Warning"; $errDetails = "無回應 - 可能斷線" }
            } else {
                if ($finalStatus -eq "Offline") { $errDetails = "軟體離線 - 網路通暢" }
            }
        }

        $printedArray = @()
        if ($logsByPrinter.ContainsKey($pName)) {
            $printedArray = $logsByPrinter[$pName].ToArray()
        }

        $obj = New-Object PSObject
        $obj | Add-Member NoteProperty Name $pName
        $obj | Add-Member NoteProperty Status $finalStatus
        $obj | Add-Member NoteProperty Jobs ($p.JobCount -as [int])
        $obj | Add-Member NoteProperty IP $pIP
        $obj | Add-Member NoteProperty Location ($p.Location -as [string])
        $obj | Add-Member NoteProperty Comment ($p.Comment -as [string])
        $obj | Add-Member NoteProperty Driver ($p.DriverName -as [string])
        $obj | Add-Member NoteProperty PortName $p.PortName
        $obj | Add-Member NoteProperty ShareName ($p.ShareName -as [string])
        $obj | Add-Member NoteProperty ErrorDetails $errDetails 
        $obj | Add-Member NoteProperty printed $printedArray
        $obj | Add-Member NoteProperty LastUpdated $timestamp
        $results.Add($obj)
    }
    return $results
}

function Test-PrinterHealth {
    Cleanup-OldLogs
    $printers = Get-PrinterStatusData
    $batchAlerts = New-Object System.Collections.Generic.List[string]
    $stuck = 0
    
    if ($enableAutoCleanup) {
        $zombies = Get-WmiObject Win32_PrintJob | Where-Object { $_.JobStatus -like "*Error*" -or $_.JobStatus -like "*Deleting*" }
        if ($zombies) { foreach($z in $zombies){ $batchAlerts.Add("自癒清理: $($z.JobId)"); $z.Delete() } }
    }

    foreach ($p in $printers) {
        $n = $p.Name; $s = $p.Status; $j = $p.Jobs
        if ($global:IsFirstRun) { if($s -like "Offline*"){$global:ExcludedPrinters[$n]=$true}; $global:LastQueueCount[$n]=$j; continue }
        if ($global:ExcludedPrinters.ContainsKey($n)) { if($s -eq "Ready" -or $s -eq "Printing"){$global:ExcludedPrinters.Remove($n)}; continue }
        
        if ($s -eq "Error" -or $s -eq "Warning") {
            $global:PrinterErrorCount[$n]++
            if ($global:PrinterErrorCount[$n] -eq $errorThreshold) { 
                $msg = "● [異常] $n $s"; if($p.ErrorDetails){$msg+=" ($($p.ErrorDetails))"}; $batchAlerts.Add($msg) 
            }
        } else {
            if ($global:PrinterErrorCount[$n] -ge $errorThreshold) { $batchAlerts.Add("○ [恢復] $n") }
            $global:PrinterErrorCount[$n] = 0
        }
        if ($j -ge $queueThreshold -and $j -ge $global:LastQueueCount[$n]) {
            $global:QueueStuckCount[$n]++
            if ($global:QueueStuckCount[$n] -eq $queueStuckLimit) { $batchAlerts.Add("?? [堵塞] $n 佇列停滯"); $stuck++ }
        } else { $global:QueueStuckCount[$n] = 0 }
        $global:LastQueueCount[$n] = $j
    }

    if ($enableAutoHeal -and $stuck -ge $maxStuckPrinters) { Invoke-SpoolerSelfHealing -reason "多台堵塞" }
    if ($global:IsFirstRun) { $global:IsFirstRun = $false; return }
    if ($batchAlerts.Count -gt 0 -and $enableAdminNotifications) { Send-SysAdminNotify -content ([string]::Join("`n", $batchAlerts)) -title "印表機告警" }
}

# -------------------------------------------------------------------------
# 3. 主程序 (HttpListener)
# -------------------------------------------------------------------------
Write-ApiLog "----------------------------------------" -Color Cyan
Write-ApiLog " Print Server API & Monitor v17.52 " -Color Cyan
Write-ApiLog "----------------------------------------" -Color Cyan

# A. 防火牆設定
Setup-FirewallRule $port

# B. PDF 閱讀器偵測
foreach ($path in $pdfReaderPaths) { if (Test-Path $path) { $global:ValidPdfReader = $path; break } }
if ($global:ValidPdfReader) { Write-ApiLog "PDF Reader: OK ($global:ValidPdfReader)" -Color Green }
else { Write-ApiLog "PDF Reader: Not Found (Fallback to Shell)" -Color Yellow }

# C. 通知伺服器偵測
if ($enableNotifyHealthCheck) {
    if (Test-TcpConnection $notifyIp $notifyPort $notifyTimeoutMs) { Write-ApiLog "Notify Server: OK" -Color Green }
    else { $global:IsNotifyServerOnline = $false; Write-ApiLog "Notify Server: Offline (Notifications Disabled)" -Color Red }
}

# 網路芳鄰預先認證 (解決 UNC 權限存取拒絕問題)
if ($enableDriveMapping -and -not [string]::IsNullOrEmpty($networkUsername)) {
    Write-ApiLog "正在建立 UNC 網路路徑認證 ($mappedDriveUncPath)..." -Color Yellow
    try { net use "$mappedDriveUncPath" /delete /y 2>&1 | Out-Null } catch {}
    try {
        $cmd = "net use `"$mappedDriveUncPath`" `"$networkPassword`" /user:`"$networkUsername`" /persistent:no"
        Invoke-Expression $cmd 2>&1 | Out-Null
        Write-ApiLog ">>> UNC 網路路徑認證成功！" -Color Green
    } catch {
        Write-ApiLog "!!! UNC 網路路徑認證失敗: $($_.Exception.Message)" -Color Red
    }
}

# D. 啟動 Web Server (含重試機制)
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
$started = $false
$retryCount = 0

while (-not $started -and $retryCount -lt 5) {
    try {
        $listener.Start()
        $started = $true
        Write-ApiLog "--- 服務已啟動，監聽 Port $port ---"
    } catch {
        $retryCount++
        Write-ApiLog "!!! 無法綁定 Port $port (嘗試 $retryCount/5): $($_.Exception.Message)" -Color Red
        Start-Sleep -Seconds 2
    }
}

if (-not $started) {
    Write-ApiLog "`n[嚴重錯誤] 服務啟動失敗！Port $port 可能被佔用或權限不足。" -Color Red
    Write-ApiLog "請嘗試以「系統管理員身分」執行，或檢查是否有殘留的 PowerShell 程序。"
    Write-ApiLog "程式將立即終止。"
    exit
}

# E. 主迴圈
$nextCheck = Get-Date; $nextHeart = Get-Date; $contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        if ($now -ge $nextHeart) { Write-ApiLog "[Heartbeat] 服務運作中..." -Color DarkGray; $nextHeart = $now.AddSeconds(60) } 
        
        # 監控邏輯
        if ($now -ge $nextCheck) {
            $day = $now.DayOfWeek.ToString()
            if (($now.Hour -ge $monitorStartHour) -and ($now.Hour -lt $monitorEndHour) -and ($monitorDays -contains $day)) {
                Test-PrinterHealth
            }
            $nextCheck = $now.AddSeconds($checkIntervalSec)
        }

        # Cron 排程 (觸發深度自癒與檔案輪替)
        if ($enableScheduledHeal) {
            if ($global:LastCronRunTime -eq $null -or ($now.Minute -ne $global:LastCronRunTime.Minute -or $now.Hour -ne $global:LastCronRunTime.Hour)) {
                if (Test-CronMatch $scheduledHealCron $now) {
                    Invoke-SpoolerSelfHealing -reason "Cron 排程"
                    Rotate-PrintLog
                    $global:LastCronRunTime = $now
                }
            }
        }

        # HTTP 請求處理
        if ($null -eq $contextTask) { $contextTask = $listener.BeginGetContext($null, $null) }
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { continue }

        $context = $listener.EndGetContext($contextTask); $contextTask = $null
        $req = $context.Request; $res = $context.Response; $path = $req.Url.AbsolutePath.ToLower()
        Write-ApiLog ">>> [REQ] $($req.RemoteEndPoint) $path"

        $res.AddHeader("Access-Control-Allow-Origin", "*")
        $res.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $res.AddHeader("Access-Control-Allow-Headers", "*") 

        if ($req.HttpMethod -eq "OPTIONS") { $res.StatusCode = 200; $res.Close(); continue }

        $out = @{ "success"=$false; "message"=""; "data"=$null }
        $handledBinary = $false
        
        if ($req.Headers["X-API-KEY"] -ne $apiKey) { $res.StatusCode = 401 }
        else {
            if ($path -eq "/printers") { 
                $out.data = Get-PrinterStatusData
                $out.success = $true
                $out.message = "OK"
                $out.isLandSystem = (Test-Path $printLogFilePath -PathType Leaf) # 判斷是否為地政系統
            }
            elseif ($path -eq "/server/logs") {
                $logF = Join-Path $logPath "PrintApi_$(Get-Date -Format 'yyyy-MM-dd').log"
                if (Test-Path $logF) {
                    $cnt = 100; $l = Get-Utf8QueryParam $req "lines"; if($l -match "^\d+$"){$cnt=[int]$l}
                    $out.data = Get-Content $logF | Select-Object -Last $cnt; $out.success = $true
                } else { $out.message = "No Log" }
            }
            elseif ($path -eq "/server/printlog") {
                try {
                    $logData = @(Get-PrintLogs)
                    $out.data = $logData
                    $out.success = $true
                    if ($logData.Length -eq 0) {
                        $out.message = "當日 ($((Get-Date).ToString('yyyy-MM-dd'))) 尚無任何列印紀錄。"
                    } else {
                        $out.message = "已成功讀取當日列印紀錄，共 $($logData.Length) 筆。"
                    }
                } catch {
                    $out.success = $false
                    $out.message = $_.Exception.Message
                    Write-ApiLog "!!! [Error] $($_.Exception.Message)"
                }
            }
            elseif ($path -eq "/server/applyforms") {
                # 讀取地政表單，依賴 UNC 掛載
                $targetDir = ""
                if ($enableDriveMapping -and -not [string]::IsNullOrEmpty($mappedDriveUncPath)) {
                    $targetDir = Join-Path $mappedDriveUncPath "temp"
                }

                if ([string]::IsNullOrEmpty($targetDir) -or -not (Test-Path $targetDir)) {
                    $out.success = $false
                    $out.message = "找不到表單目錄，請確認 UNC 路徑是否已正確設定或掛載: $targetDir"
                } else {
                    $filter = Get-Utf8QueryParam $req "filter"
                    if ([string]::IsNullOrEmpty($filter)) { $filter = "cer_ApplyForm_*.pdf" }

                    $today = (Get-Date).Date
                    $files = Get-ChildItem -Path $targetDir -Filter $filter -ErrorAction SilentlyContinue | 
                             Where-Object { $_.LastWriteTime.Date -eq $today } | 
                             Sort-Object LastWriteTime -Descending

                    $forms = New-Object System.Collections.ArrayList
                    if ($files) {
                        foreach ($f in $files) {
                            $item = @{
                                name = $f.Name
                                path = $f.FullName -replace "\\", "/"
                                time = $f.LastWriteTime.ToString("HH:mm:ss")
                                size = "{0:N2} KB" -f ($f.Length / 1KB)
                            }
                            [void]$forms.Add($item)
                        }
                    }
                    $out.success = $true
                    $out.data = $forms.ToArray()
                    $out.message = "成功取得 $($forms.Count) 筆表單"
                }
            }
            elseif ($path -eq "/printer/printed") {
                $n = Get-Utf8QueryParam $req "name"
                if ([string]::IsNullOrEmpty($n)) {
                    $out.success = $false
                    $out.message = "缺少 name 參數，無法查詢該印表機紀錄。"
                } else {
                    try {
                        $allLogs = @(Get-PrintLogs)
                        $printedArray = New-Object System.Collections.ArrayList
                        foreach ($log in $allLogs) {
                            if ($log.printer -eq $n) {
                                $item = New-Object PSObject
                                $item | Add-Member NoteProperty date $log.date
                                $item | Add-Member NoteProperty time $log.time
                                $item | Add-Member NoteProperty path $log.path
                                [void]$printedArray.Add($item)
                            }
                        }
                        $resultObj = New-Object PSObject
                        $resultObj | Add-Member NoteProperty printer $n
                        $resultObj | Add-Member NoteProperty printed $printedArray.ToArray()
                        
                        $out.data = @($resultObj)
                        $out.success = $true
                        if ($printedArray.Count -eq 0) { $out.message = "印表機 [$n] 當日尚無任何列印紀錄。" } 
                        else { $out.message = "已成功讀取印表機 [$n] 的紀錄，共印過 $($printedArray.Count) 筆檔案。" }
                    } catch {
                        $out.success = $false; $out.message = $_.Exception.Message
                    }
                }
            }
            elseif ($path -eq "/printer/re-print") {
                $n = Get-Utf8QueryParam $req "name"
                $rawPath = Get-Utf8QueryParam $req "path"
                $fPath = Resolve-VirtualPath $rawPath
                Write-ApiLog ">>> [DEBUG] Re-Print: Name='$n', Path='$fPath'"
                
                # --- 資安防護：驗證檔案是否在紀錄中，或是來自合法的 UNC 表單目錄 ---
                $isAuthorized = $false
                if (-not [string]::IsNullOrEmpty($rawPath)) {
                    $logs = @(Get-PrintLogs)
                    $normRaw = $rawPath.Replace("/", "\")
                    foreach ($log in $logs) { if ($log.path.Replace("/", "\") -eq $normRaw) { $isAuthorized = $true; break } }

                    if (-not $isAuthorized -and $enableDriveMapping -and -not [string]::IsNullOrEmpty($mappedDriveUncPath)) {
                        if ($fPath.StartsWith($mappedDriveUncPath, [System.StringComparison]::OrdinalIgnoreCase)) {
                            $isAuthorized = $true
                            Write-ApiLog ">>> [DEBUG] UNC 目錄表單已授權重印: $fPath" -Color Cyan
                        }
                    }
                }

                if (-not $isAuthorized) {
                    $out.success = $false
                    $out.message = "安全性阻擋：拒絕重印未列於當日紀錄或非合法 UNC 表單目錄中的檔案。"
                    Write-ApiLog "!!! [SECURITY] 嘗試重印未授權的檔案: $rawPath" -Color Red
                    $res.StatusCode = 403
                }
                elseif ([string]::IsNullOrEmpty($n) -or [string]::IsNullOrEmpty($fPath)) {
                    $out.success = $false; $out.message = "缺少 name 或 path 參數，無法執行重印。"
                } else {
                    $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                    if ($p) {
                        if (-not (Test-Path $fPath -PathType Leaf)) {
                            $out.success = $false; $out.message = "伺服器上找不到指定的檔案 (或權限不足): $fPath"
                        } else {
                            $dup = Get-Utf8QueryParam $req "duplex"
                            $restoreDup = $false; $oldDup = $null
                            if ($dup) {
                                if (Get-Command Set-PrintConfiguration -ErrorAction SilentlyContinue) {
                                    try {
                                        $cfg = Get-PrintConfiguration -PrinterName $n -ErrorAction Stop
                                        $oldDup = $cfg.DuplexingMode
                                        $tDup = "OneSided"
                                        if ($dup -eq "1" -or $dup -eq "long") { $tDup = "TwoSidedLongEdge" }
                                        elseif ($dup -eq "2" -or $dup -eq "short") { $tDup = "TwoSidedShortEdge" }
                                        if ($oldDup -ne $tDup) { Set-PrintConfiguration -PrinterName $n -DuplexingMode $tDup; $restoreDup=$true }
                                    } catch {}
                                }
                            }
                            try {
                                if ($global:ValidPdfReader) {
                                    Start-Process -FilePath $global:ValidPdfReader -ArgumentList "/t `"$fPath`" `"$n`"" -WindowStyle Hidden
                                } else {
                                    Start-Process -FilePath $fPath -Verb PrintTo -ArgumentList "`"$n`"" -WindowStyle Hidden
                                }
                                $out.success = $true; $out.message = "已成功發送指令至印表機 [$n] 重新列印: $fPath"
                            } catch { $out.success = $false; $out.message = "列印失敗: $($_.Exception.Message)" }
                            if ($restoreDup) { try{ Set-PrintConfiguration -PrinterName $n -DuplexingMode $oldDup }catch{} }
                        }
                    } else { $out.success = $false; $out.message = "找不到指定的印表機: $n" }
                }
            }
            elseif ($path -eq "/printer/preview") {
                $rawPath = Get-Utf8QueryParam $req "path"
                $fPath = Resolve-VirtualPath $rawPath
                Write-ApiLog ">>> [DEBUG] Preview PDF: Path='$fPath'"
                
                # --- 資安防護：驗證檔案是否在紀錄中，或是來自合法的 UNC 表單目錄 ---
                $isAuthorized = $false
                if (-not [string]::IsNullOrEmpty($rawPath)) {
                    $logs = @(Get-PrintLogs)
                    $normRaw = $rawPath.Replace("/", "\")
                    foreach ($log in $logs) { if ($log.path.Replace("/", "\") -eq $normRaw) { $isAuthorized = $true; break } }

                    if (-not $isAuthorized -and $enableDriveMapping -and -not [string]::IsNullOrEmpty($mappedDriveUncPath)) {
                        if ($fPath.StartsWith($mappedDriveUncPath, [System.StringComparison]::OrdinalIgnoreCase)) {
                            $isAuthorized = $true
                            Write-ApiLog ">>> [DEBUG] UNC 目錄表單已授權預覽: $fPath" -Color Cyan
                        }
                    }
                }

                if (-not $isAuthorized) {
                    $out.success = $false
                    $out.message = "安全性阻擋：拒絕預覽未列於當日紀錄或非合法 UNC 表單目錄中的檔案。"
                    Write-ApiLog "!!! [SECURITY] 嘗試預覽未授權的檔案: $rawPath" -Color Red
                    $res.StatusCode = 403
                }
                elseif ([string]::IsNullOrEmpty($fPath) -or -not (Test-Path $fPath -PathType Leaf)) {
                    $out.success = $false
                    $out.message = "找不到檔案，可能已被清理或無權限存取該路徑: $fPath"
                    Write-ApiLog "!!! [DEBUG] Preview 找不到檔案: $fPath" -Color Yellow
                } else {
                    try {
                        $fileBytes = [System.IO.File]::ReadAllBytes($fPath)
                        $res.ContentType = "application/pdf"
                        $res.AddHeader("Access-Control-Expose-Headers", "Content-Disposition")
                        $res.AddHeader("Content-Disposition", "inline; filename=`"preview.pdf`"")
                        $res.ContentLength64 = $fileBytes.Length
                        $res.OutputStream.Write($fileBytes, 0, $fileBytes.Length)
                        $res.Close()
                        $handledBinary = $true
                        Write-ApiLog ">>> [DEBUG] Preview PDF 回傳成功 ($($fileBytes.Length) bytes)"
                    } catch {
                        $out.success = $false
                        $out.message = "檔案讀取失敗: $($_.Exception.Message)"
                    }
                }
            }
            elseif ($path -eq "/server/restart-script") {
                $out.success = $true; $out.message = "Restarting..."
                Write-ApiLog ">>> 重啟腳本指令"
                $restartScript = $true
            }
            elseif ($path -eq "/server/restart-computer") {
                $out.success = $true; $out.message = "Rebooting OS in 5s..."
                Write-ApiLog ">>> 重啟電腦指令"
                $restartComputer = $true
            }
            elseif ($path -eq "/printer/update") {
                $n=Get-Utf8QueryParam $req "name"; $l=Get-Utf8QueryParam $req "location"; $c=Get-Utf8QueryParam $req "comment"
                Write-ApiLog ">>> [DEBUG] Update: Name='$n', Loc='$l', Com='$c'"
                
                $p=Get-WmiObject Win32_Printer|Where{$_.Name -eq $n}
                if($p){
                    if($l){$p.Location=$l}; if($c){$p.Comment=$c}
                    try{$p.Put(); $out.success=$true; $out.message="Updated"}catch{$out.message=$_.Exception.Message}
                } else { $out.message = "Not Found: $n" }
            }
            elseif ($path -eq "/printer/print-pdf") {
                if ($req.HttpMethod -eq "POST") {
                    $n = Get-Utf8QueryParam $req "name"
                    Write-ApiLog ">>> [DEBUG] PrintPDF: Name='$n'"
                    
                    $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                    if ($p) {
                         $dup = Get-Utf8QueryParam $req "duplex"
                         if ($dup) {
                            if (Get-Command Set-PrintConfiguration -ErrorAction SilentlyContinue) {
                                try {
                                    $cfg = Get-PrintConfiguration -PrinterName $n -ErrorAction Stop
                                    $oldDup = $cfg.DuplexingMode
                                    $tDup = "OneSided"
                                    if ($dup -eq "1" -or $dup -eq "long") { $tDup = "TwoSidedLongEdge" }
                                    elseif ($dup -eq "2" -or $dup -eq "short") { $tDup = "TwoSidedShortEdge" }
                                    if ($oldDup -ne $tDup) { Set-PrintConfiguration -PrinterName $n -DuplexingMode $tDup; $restoreDup=$true }
                                } catch {}
                            }
                         }

                         $fName = "Upload_$(Get-Date -Format 'yyyyMMdd_HHmmss').pdf"
                         $fPath = Join-Path $uploadPath $fName
                         $fs = New-Object System.IO.FileStream($fPath, [System.IO.FileMode]::Create)
                         $buf = New-Object byte[] 8192
                         do { $r=$req.InputStream.Read($buf,0,$buf.Length); if($r -gt 0){$fs.Write($buf,0,$r)} } while($r -gt 0)
                         $fs.Close()

                         try {
                             if ($global:ValidPdfReader) {
                                 Start-Process -FilePath $global:ValidPdfReader -ArgumentList "/t `"$fPath`" `"$n`"" -WindowStyle Hidden
                             } else {
                                 Start-Process -FilePath $fPath -Verb PrintTo -ArgumentList "`"$n`"" -WindowStyle Hidden
                             }
                             $out.success=$true
                         } catch { $out.message=$_.Exception.Message }

                         if ($restoreDup) { try{ Set-PrintConfiguration -PrinterName $n -DuplexingMode $oldDup }catch{} }
                    } else { $out.message = "Not Found: $n" }
                }
            }
            elseif ($path -eq "/printer/status") {
                $n = Get-Utf8QueryParam $req "name"
                Write-ApiLog ">>> [DEBUG] Status: Name='$n'"
                $data = Get-PrinterStatusData
                foreach($item in $data){if($item.Name -eq $n){$out.data=$item; $out.success=$true; break}}
            }
            elseif ($path -eq "/printer/refresh") {
                $n = Get-Utf8QueryParam $req "name"
                Write-ApiLog ">>> [DEBUG] Refresh: Name='$n'"
                $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                if($p){ $p.Pause(); Start-Sleep -m 500; $p.Resume(); $out.success=$true }
            }
            elseif ($path -eq "/printer/clear") {
                $n = Get-Utf8QueryParam $req "name"
                Write-ApiLog ">>> [DEBUG] Clear: Name='$n'"
                $js = Get-WmiObject Win32_PrintJob | Where {$_.Name -like "*$n*"}
                if($js){ foreach($j in $js){$j.Delete()}; $out.success=$true } else { $out.success=$true } 
            }
            elseif ($path -eq "/service/restart-spooler") {
                try { Restart-Service "Spooler" -Force; $out.success=$true } catch { $out.message=$_.Exception.Message }
            }
            elseif ($path -eq "/service/self-heal") {
                Invoke-SpoolerSelfHealing -reason "API Trigger"; $out.success=$true
            }
            else { $res.StatusCode = 404 }
        }

        # 如果沒有被宣告處理為二進位流 ($handledBinary = $false)，則走原有的 JSON 輸出模式
        if (-not $handledBinary) {
            $json = ConvertTo-SimpleJson $out
            $buf = [System.Text.Encoding]::UTF8.GetBytes($json)
            $res.ContentType = "application/json"
            try {
                $res.ContentLength64 = $buf.Length
                $res.OutputStream.Write($buf, 0, $buf.Length)
                $res.Close()
            } catch { Write-ApiLog ">>> [Warn] Client disconnected early" }
        }

        if ($restartScript) {
            Write-ApiLog ">>> 重啟中..."
            try { $listener.Stop(); $listener.Close() } catch {}
            Start-Process powershell.exe -ArgumentList "-ExecutionPolicy Bypass -File `"$($MyInvocation.MyCommand.Definition)`"" -WindowStyle Hidden
            exit
        }
        if ($restartComputer) {
            Write-ApiLog ">>> 關機中..."
            try { $listener.Stop(); $listener.Close() } catch {}
            if ($enableAdminNotifications) { Send-SysAdminNotify -content "API：收到管理員指令，伺服器即將在 5 秒後重新啟動。" -title "系統操作" }
            Start-Process "shutdown.exe" -ArgumentList "/r /t 5 /f /d p:4:1"
            exit
        }

    } catch { 
        Write-ApiLog "!!! [Error] $($_.Exception.Message)"
        $contextTask = $null 
    }
}