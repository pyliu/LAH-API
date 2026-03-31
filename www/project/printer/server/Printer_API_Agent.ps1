<#
.SYNOPSIS
    資深系統整合工程師實作版本 - Print Server HTTP API & Proactive Monitor
    版本：v17.68 (終極靜音排程與防阻塞優化版)
    
    修正紀錄：
    1. [防擾民機制] 針對 Cron 排程執行的例行維護，若無過期暫存檔需清理 ($clearedCount = 0)，則僅寫入 Log，不發送推播通知。
    2. [API 阻塞修復] 將 /service/restart-spooler 與 /service/self-heal 改為「先回應、後執行」的延遲觸發架構，徹底解決前端無回應 (Timeout) 的問題。
    3. [防卡死防線] 強化 Invoke-SpoolerSelfHealing 與重啟功能，在 Stop-Service 後加入強制 Stop-Process spoolsv，保證服務重啟 100% 成功。
    4. [CRON 邏輯修復] 解決隔日排程不觸發的問題 (將防止重複執行的檢查，從單純比對時/分，升級為精確到日的 yyyyMMddHHmm 比對)。
    5. [CRON 解析強化] 使用正規表示式分隔空白，提高對 .env 檔中多餘空白字元的容錯度。
    6. [編碼安全] 徹底移除所有實體 Emoji，改用 [char]::ConvertFromUtf32() 動態生成，完美相容 BIG5 / ANSI 存檔。
    7. [程式碼全展開] 嚴格禁止壓縮，完整保留所有 API 路由實作 (re-print, preview, print-pdf 等) 與系統函數。
    8. [WS 安全認證] 實作 WebSocket 連線金鑰驗證，統一使用 $apiKey 進行安全防護。
    9. [佇列極速推播] 改用 Win32_PrintJob 分析配合手動 JSON 組裝，實現毫秒級無阻塞廣播。

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
    [提示] 測試指令範例 (CMD):
    curl -H "X-API-KEY: %API_KEY%" http://localhost:8888/printers
#>

# -------------------------------------------------------------------------
# 1. 基礎設定區 (由 Printer_API_Agent.env 外部載入)
# -------------------------------------------------------------------------

$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } elseif ($MyInvocation.MyCommand.Path) { Split-Path $MyInvocation.MyCommand.Path } else { $PWD.Path }
$envFile = Join-Path $scriptDir "Printer_API_Agent.env"
$envConfig = @{}

if (Test-Path $envFile) {
    try {
        foreach ($line in Get-Content $envFile -Encoding UTF8) {
            $line = $line.Trim()
            if ($line.StartsWith("#") -or $line -eq "") { 
                continue 
            }
            $parts = $line -split '=', 2
            if ($parts.Length -eq 2) {
                $key = $parts[0].Trim()
                $val = $parts[1].Trim()
                if ($val -match '^"(.*)"$') { 
                    $val = $matches[1] 
                } elseif ($val -match "^'(.*)'$") { 
                    $val = $matches[1] 
                }
                $envConfig[$key] = $val
            }
        }
        Write-Host ">>> [System] 已成功載入外部設定檔: Printer_API_Agent.env" -ForegroundColor Cyan
    } catch {
        Write-Host "!!! [System] 讀取 Printer_API_Agent.env 失敗" -ForegroundColor Yellow
    }
}

function Get-EnvString($key, $default) { 
    if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return $envConfig[$key] } 
    return $default 
}

function Get-EnvInt($key, $default) { 
    if ($envConfig.Contains($key) -and $envConfig[$key] -match '^\d+$') { return [int]$envConfig[$key] } 
    return $default 
}

function Get-EnvBool($key, $default) { 
    if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return ($envConfig[$key] -match '^(true|1|yes)$') } 
    return $default 
}

function Get-EnvArray($key, [string[]]$default) { 
    if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { 
        return @($envConfig[$key] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }) 
    } 
    return $default 
}

$port               = Get-EnvInt "PORT" 8888
$apiKey             = Get-EnvString "API_KEY" "YourSecretApiKey123"      
$logPath            = Get-EnvString "LOG_PATH" "C:\Temp"
$uploadPath         = Get-EnvString "UPLOAD_PATH" "C:\Temp\Uploads"           
$maxLogSizeBytes    = Get-EnvInt "MAX_LOG_SIZE_BYTES" 10485760                     
$maxHistory         = Get-EnvInt "MAX_HISTORY" 5                          
$logRetentionDays   = Get-EnvInt "LOG_RETENTION_DAYS" 7                   
$printLogFilePath   = Get-EnvString "PRINT_LOG_FILE_PATH" "C:\printlog"

$defaultPdfReaders  = @(
    "C:\Temp\SumatraPDF.exe",
    "C:\Program Files\SumatraPDF\SumatraPDF.exe",
    "C:\Program Files (x86)\SumatraPDF\SumatraPDF.exe",
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
$manualExcludePrinters = Get-EnvArray "MANUAL_EXCLUDE_PRINTERS" @()

$enableDriveMapping = Get-EnvBool "ENABLE_DRIVE_MAPPING" $true
$mappedDriveLetter  = Get-EnvString "MAPPED_DRIVE_LETTER" "Z:"
$mappedDriveUncPath = Get-EnvString "MAPPED_DRIVE_UNC_PATH" "\\220.1.34.43\land_adm_web_ha\cer"
$networkUsername    = Get-EnvString "NETWORK_USERNAME" ""
$networkPassword    = Get-EnvString "NETWORK_PASSWORD" ""

# 全局狀態與快取變數
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

$global:PrintLogCache = @()
$global:PrintLogLastWriteTime = [DateTime]::MinValue
$global:PrintLogDirty = $true
$global:WSClients = New-Object System.Collections.ArrayList

$global:SpoolerDirty = $false
$global:LastSpoolerPushTime = [DateTime]::MinValue
$spoolerPath = "C:\Windows\System32\spool\PRINTERS"
$global:KnownPrinters = @()

# -------------------------------------------------------------------------
# 2. 核心函數庫
# -------------------------------------------------------------------------

function ConvertTo-SimpleJson {
    param($InputObject)
    
    if ($null -eq $InputObject) { 
        return "null" 
    }
    
    if ($InputObject -is [string]) { 
        $escapedStr = $InputObject.Replace('\', '\\').Replace('"', '\"').Replace("`n", "\n").Replace("`r", "\r").Replace("`t", "\t")
        return """$escapedStr""" 
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
            $pairs.Add("""$key"":" + (ConvertTo-SimpleJson $InputObject[$key])) 
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
            $objPairs.Add("""$($prop.Name)"":" + (ConvertTo-SimpleJson $prop.Value)) 
        }
    } catch { 
        return """$($InputObject.ToString())""" 
    }
    
    if ($objPairs.Count -gt 0) { 
        return "{" + [string]::Join(",", $objPairs) + "}" 
    } else { 
        return """$($InputObject.ToString())""" 
    }
}

function Write-ApiLog {
    param([string]$message, [ConsoleColor]$Color = "Gray")
    
    try {
        if (-not (Test-Path $logPath)) { 
            [void](New-Item -ItemType Directory -Path $logPath -Force) 
        }
        
        $today = Get-Date -Format "yyyy-MM-dd"
        $fullPath = Join-Path $logPath "PrintApi_$today.log"
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
        
        if (Test-Path $fullPath) {
            if ((Get-Item $fullPath).Length -ge $maxLogSizeBytes) {
                if (Test-Path "$fullPath.$maxHistory") { 
                    Remove-Item "$fullPath.$maxHistory" -Force 
                }
                for ($i = $maxHistory - 1; $i -ge 1; $i--) { 
                    $src = "$fullPath.$i"
                    $dest = "$fullPath.$($i + 1)"
                    if (Test-Path $src) { 
                        Move-Item $src $dest -Force 
                    } 
                }
                Move-Item $fullPath "$fullPath.1" -Force
            }
        }
        
        Add-Content -Path $fullPath -Value $logEntry
        
        if ($Color -ne "Gray") { 
            Write-Host $logEntry -ForegroundColor $Color 
        } else { 
            Write-Host $logEntry 
        }
    } catch {}
}

function Cleanup-OldLogs {
    try {
        if (Test-Path $logPath) {
            $limitDate = (Get-Date).AddDays(-$logRetentionDays)
            $oldFiles = Get-ChildItem -Path $logPath -Filter "PrintApi_*.log*" | Where-Object { $_.LastWriteTime -lt $limitDate }
            if ($null -ne $oldFiles) { 
                foreach ($file in $oldFiles) { 
                    Remove-Item $file.FullName -Force 
                } 
            }
        }
    } catch {}
}

function Rotate-PrintLog {
    if (-not (Test-Path $printLogFilePath -PathType Leaf)) { 
        return 
    }
    
    $backupName = "printlog.$((Get-Date).ToString('yyyyMMdd'))"
    $backupPath = Join-Path $logPath $backupName
    
    try {
        if (Test-Path $backupPath) { 
            Remove-Item $backupPath -Force 
        }
        Move-Item -Path $printLogFilePath -Destination $backupPath -Force
        New-Item -Path $printLogFilePath -ItemType File -Force | Out-Null
        
        Write-ApiLog ">>> [系統維護] 已備份並重置列印紀錄 -> $backupName" -Color Green
        
        $limitDate = (Get-Date).AddDays(-7)
        $oldLogs = Get-ChildItem -Path $logPath -Filter "printlog.*" | Where-Object { $_.LastWriteTime -lt $limitDate }
        
        if ($oldLogs) { 
            foreach ($file in $oldLogs) { 
                Remove-Item $file.FullName -Force 
            } 
        }
        
        $global:PrintLogCache = @()
        $global:PrintLogLastWriteTime = [DateTime]::MinValue
        $global:PrintLogDirty = $true
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
        } else { 
            Write-ApiLog ">>> 防火牆規則已存在。" -Color Green 
        }
    } catch {
        Write-ApiLog "!!! 防火牆設定失敗: $($_.Exception.Message)" -Color Red
    }
}

function Test-TcpConnection {
    param([string]$target, [int]$port, [int]$timeoutMs)
    
    $tcp = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $tcp.BeginConnect($target, $port, $null, $null)
        if ($async.AsyncWaitHandle.WaitOne($timeoutMs, $false)) { 
            $tcp.EndConnect($async)
            return $true 
        }
        return $false
    } catch { 
        return $false 
    } finally { 
        if ($tcp.Connected) { 
            $tcp.Close() 
        } else { 
            $tcp.Close() 
        } 
    }
}

function Send-SysAdminNotify {
    param([string]$content, [string]$title = "印表機系統通知")
    
    if ($null -eq $notifyChannels -or $notifyChannels.Count -eq 0) { return }
    if ($enableNotifyHealthCheck -and (-not $global:IsNotifyServerOnline)) { return }

    try {
        $localIp = "127.0.0.1"
        try { 
            $ipConfig = Get-WmiObject Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled }
            if ($null -ne $ipConfig) { 
                if ($ipConfig -is [array]) { 
                    $localIp = $ipConfig[0].IPAddress[0] 
                } else { 
                    $localIp = $ipConfig.IPAddress[0] 
                } 
            } 
        } catch { }
        
        $fields = @{ 
            "type"="add_notification"
            "title"=$title
            "content"=$content
            "priority"="3"
            "sender"="$($env:COMPUTERNAME) ($localIp)"
            "from_ip"=$localIp 
        }
        
        $encodedParts = New-Object System.Collections.Generic.List[string]
        foreach ($key in $fields.Keys) { 
            $encodedParts.Add("$key=$([System.Uri]::EscapeDataString($fields[$key]))") 
        }
        
        if ($null -ne $notifyChannels) { 
            foreach ($chan in $notifyChannels) { 
                $encodedParts.Add("channels[]=$([System.Uri]::EscapeDataString($chan))") 
            } 
        }
        $postBody = [string]::Join("&", $encodedParts)
        
        $req = [System.Net.WebRequest]::Create($notifyUrl)
        $req.Method = "POST"
        $req.ContentType = "application/x-www-form-urlencoded"
        $req.Timeout = 1000 
        
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($postBody)
        $req.ContentLength = $bytes.Length
        
        $reqStream = $req.GetRequestStream()
        $reqStream.Write($bytes, 0, $bytes.Length)
        $reqStream.Close()
        
        $resp = $req.GetResponse()
        $resp.Close()
    } catch {
        Write-ApiLog "!!! 通知發送失敗: $($_.Exception.Message)" -Color Red
    }
}

function Invoke-SpoolerSelfHealing {
    param([string]$reason)
    
    $isCron = ($reason -match "Cron" -or $reason -match "排程")
    $isApi = ($reason -match "API")
    
    # [BIG5 完美解法] 在記憶體中動態生成 Emoji，檔案維持純 ASCII 就不會變亂碼
    $eAlert  = [char]::ConvertFromUtf32(0x1F6A8) # 警車燈
    $eRecyc  = [char]::ConvertFromUtf32(0x267B)  # 循環標誌
    $eTool   = [char]::ConvertFromUtf32(0x1F6E0) # 工具
    $eCheck  = [char]::ConvertFromUtf32(0x2705)  # 綠色打勾
    $eBroom  = [char]::ConvertFromUtf32(0x1F9F9) # 掃把
    $eCross  = [char]::ConvertFromUtf32(0x274C)  # 紅色叉叉
    
    Write-ApiLog "!!! [程序啟動] 觸發原因: $reason"
    
    try {
        # [防卡死優化] 強制停止服務，並在背景確保進程被獵殺，避免 API 掛死
        Stop-Service "Spooler" -Force -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        
        $spoolerProc = Get-Process -Name "spoolsv" -ErrorAction SilentlyContinue
        if ($spoolerProc) {
            $spoolerProc | Stop-Process -Force -ErrorAction SilentlyContinue
        }
        Start-Sleep -Seconds 1
        
        # 計算並清理暫存檔
        $clearedCount = 0
        if (Test-Path "C:\Windows\System32\spool\PRINTERS") { 
            $items = Get-ChildItem -Path "C:\Windows\System32\spool\PRINTERS\*" -Include *.* -Force 
            $clearedCount = @($items).Count
            if ($clearedCount -gt 0) {
                $items | Remove-Item -Force 
            }
        }
        
        Start-Service "Spooler"
        
        # 決定是否需要發送通知的邏輯旗標
        $shouldNotify = $true
        
        # 準備統一集結的訊息
        $notifyTitle = "$eAlert 自癒修復完成"
        $notifyContent = "系統偵測到異常 ($reason)，已自動完成修復。`n$eCheck Spooler 服務已重啟`n$eBroom 共清理了 $clearedCount 個佇列暫存檔"
        
        if ($isCron) {
            $notifyTitle = "$eRecyc 例行維護完成"
            $notifyContent = "系統已順利執行排程維護 ($reason)。`n$eCheck Spooler 服務已安全重置`n$eBroom 共清理了 $clearedCount 個過期暫存檔"
            
            # [防擾民優化] 如果是例行排程且沒有清到任何檔案，就不發送通知
            if ($clearedCount -eq 0) {
                $shouldNotify = $false
            }
        } elseif ($isApi) {
            $notifyTitle = "$eTool 手動維護完成"
            $notifyContent = "管理員已透過 API 手動觸發系統維護。`n$eCheck Spooler 服務已安全重置`n$eBroom 共清理了 $clearedCount 個佇列暫存檔"
        }
        
        Write-ApiLog "$eCheck [程序完成] 服務已重啟，清理了 $clearedCount 個暫存檔。" -Color Green
        
        if ($shouldNotify) {
            Send-SysAdminNotify -title $notifyTitle -content $notifyContent
        } else {
            Write-ApiLog ">>> [提示] 例行維護無暫存檔需清理，已略過推播通知防擾民。" -Color DarkGray
        }
        
    } catch {
        $err = $_.Exception.Message
        Write-ApiLog "$eCross [程序失敗] $err" -Color Red
        Send-SysAdminNotify -title "$eCross 維護/自癒失敗" -content "觸發原因: $reason`n錯誤訊息: $err" 
    }
}

function Test-CronMatch {
    param($cron, $now)
    
    if ([string]::IsNullOrEmpty($cron)) { return $false }
    
    # [CRON 解析強化] 支援多個連續空白字元的防錯處理
    $parts = $cron -split '\s+'
    if ($parts.Count -ne 5) { return $false }
    
    $min = $parts[0]
    $hour = $parts[1]
    $dom = $parts[2]
    $month = $parts[3]
    $dow = $parts[4]
    
    function Check($p, $v) {
        if ($p -eq "*") { return $true }
        if ($p -match "^(\*|\d+)/(\d+)$") { return ($v % [int]$matches[2]) -eq 0 }
        if ($p -match ",") { 
            foreach($i in $p.Split(",")){ 
                if([int]$i -eq $v){ return $true } 
            }
            return $false 
        }
        return [int]$p -eq $v
    }
    
    return (Check $min $now.Minute) -and 
           (Check $hour $now.Hour) -and 
           (Check $dom $now.Day) -and 
           (Check $month $now.Month) -and 
           (Check $dow [int]$now.DayOfWeek)
}

function Get-Utf8QueryParam { 
    param($request, $key)
    
    $rawUrl = $request.RawUrl
    if ($rawUrl -match "[?&]$key=([^&]*)") {
        $encodedVal = $matches[1]
        $encodedVal = $encodedVal.Replace("+", "%20")
        try { 
            return [System.Uri]::UnescapeDataString($encodedVal) 
        } catch { 
            return $null 
        }
    }
    return $null
}

function Resolve-VirtualPath {
    param([string]$rawPath)
    
    if ([string]::IsNullOrEmpty($rawPath)) { return $null }
    
    $path = $rawPath.Replace("/", "\")
    if ($enableDriveMapping -and $path -match "^(?i)$([regex]::Escape($mappedDriveLetter))\\?(.*)$") {
        $path = Join-Path $mappedDriveUncPath $matches[1]
    }
    
    return $path
}

# --- WebSocket 廣播函數 ---
function Broadcast-WebSocketMessage {
    param([string]$JsonPayload)
    
    if ($global:WSClients.Count -eq 0) { return }
    
    $buffer = [System.Text.Encoding]::UTF8.GetBytes($JsonPayload)
    $segment = New-Object System.ArraySegment[byte]($buffer, 0, $buffer.Length)
    $deadSockets = New-Object System.Collections.ArrayList
    
    Write-ApiLog ">>> [WebSocket] 廣播訊息 ($($buffer.Length) bytes) 給 $($global:WSClients.Count) 客戶端..." -Color DarkGray
    
    foreach ($ws in $global:WSClients) {
        if ($null -ne $ws -and $ws.State -eq [System.Net.WebSockets.WebSocketState]::Open) {
            try {
                $task = $ws.SendAsync($segment, [System.Net.WebSockets.WebSocketMessageType]::Text, $true, [System.Threading.CancellationToken]::None)
                $task.Wait(200)
            } catch { 
                [void]$deadSockets.Add($ws) 
            }
        } else {
            [void]$deadSockets.Add($ws)
        }
    }
    
    foreach ($dead in $deadSockets) { 
        $global:WSClients.Remove($dead) | Out-Null 
    }
}

# --- 獨立的快取更新函數 (給 FileSystemWatcher 使用) ---
function Update-PrintLogCache {
    if (-not (Test-Path $printLogFilePath -PathType Leaf)) {
        $global:PrintLogCache = @()
        return
    }
    
    $fs = $null
    $sr = $null
    try {
        $currentFileInfo = Get-Item $printLogFilePath -ErrorAction Stop
        $currentWriteTime = $currentFileInfo.LastWriteTime
        
        if ($currentWriteTime -eq $global:PrintLogLastWriteTime) { return }

        Write-ApiLog ">>> [Cache] 偵測到紀錄變動，執行背景即時解析..." -Color Cyan
        
        $results = New-Object System.Collections.ArrayList
        $enUS = New-Object System.Globalization.CultureInfo("en-US")
        $now = Get-Date
        $todayMonthStr = $now.ToString("MMM", $enUS)
        $todayDay = $now.Day
        $todayYearStr = $now.ToString("yyyy")
        $todayFormatted = $now.ToString("yyyy-MM-dd")

        $fs = New-Object System.IO.FileStream($printLogFilePath, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
        $sr = New-Object System.IO.StreamReader($fs, [System.Text.Encoding]::Default)
        
        while (-not $sr.EndOfStream) {
            $line = $sr.ReadLine()
            if ($line -match "([a-zA-Z]{3}\s+[a-zA-Z]{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}\s+.+?\d{4})") {
                $timeStr = $matches[1]
                if ($timeStr -match "$todayMonthStr\s+0?$todayDay\b" -and $timeStr -match "$todayYearStr$") {
                    $time = ""
                    if ($timeStr -match "(\d{2}:\d{2}:\d{2})") { 
                        $time = $matches[1] 
                    }
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
        $global:PrintLogCache = $results.ToArray()
        $global:PrintLogLastWriteTime = $currentWriteTime
        Write-ApiLog ">>> [Cache] 解析完成，已快取 $($global:PrintLogCache.Length) 筆紀錄。" -Color Green

        Write-ApiLog ">>> [WebSocket] 準備觸發 [列印紀錄更新] (PRINT_LOG_UPDATE) 推播..." -Color Magenta
        $wsPayload = @{ type = "PRINT_LOG_UPDATE"; data = $global:PrintLogCache }
        Broadcast-WebSocketMessage (ConvertTo-SimpleJson $wsPayload)
        Write-ApiLog ">>> [WebSocket] [列印紀錄更新] 推播完成！" -Color Green

    } catch {
        Write-ApiLog "!!! [Cache] 讀取遇到鎖定 (稍後重試): $($_.Exception.Message)" -Color Yellow
        $global:PrintLogDirty = $true 
    } finally {
        if ($null -ne $sr) { $sr.Close(); $sr.Dispose() }
        if ($null -ne $fs) { $fs.Close(); $fs.Dispose() }
    }
}

# --- 唯讀記憶體快取 Getter ---
function Get-PrintLogs {
    if ($null -eq $global:PrintLogCache) { 
        return @() 
    }
    return $global:PrintLogCache
}

# --- 獲取所有印表機狀態 ---
function Get-PrinterStatusData {
    $results = New-Object System.Collections.Generic.List[Object]
    $portMap = @{}
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

    $logsByPrinter = @{}
    $allLogs = Get-PrintLogs
    
    if ($allLogs) {
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

    try { 
        $tcpPorts = Get-WmiObject -Class Win32_TCPIPPrinterPort -ErrorAction SilentlyContinue
        if ($tcpPorts) { 
            foreach($t in $tcpPorts) { 
                if ($t.Name) { $portMap[$t.Name] = $t.HostAddress } 
            } 
        } 
    } catch {}
    
    $wmiPrinters = Get-WmiObject -Class Win32_Printer
    $tempKnownPrinters = New-Object System.Collections.ArrayList

    foreach ($p in $wmiPrinters) {
        $pName = $p.Name
        [void]$tempKnownPrinters.Add($pName)

        $shouldSkip = $false
        foreach ($kw in $excludeKeywords) { 
            if ($pName -like "*$kw*") { $shouldSkip = $true; break } 
        }
        if ($shouldSkip) { continue }
        
        foreach ($exName in $manualExcludePrinters) { 
            if ($pName -eq $exName) { $shouldSkip = $true; break } 
        }
        if ($shouldSkip) { continue }

        $errDetails = ""
        $finalStatus = "Ready"
        
        if ($p.WorkOffline) { 
            $finalStatus = "Offline" 
        } elseif ($p.DetectedErrorState -ne 0) { 
            $finalStatus = "Error"
            switch ($p.DetectedErrorState) {
                4  { $errDetails = "4: 缺紙" } 
                5  { $errDetails = "5: 碳粉不足" } 
                6  { $errDetails = "6: 缺碳粉" }
                7  { $errDetails = "7: 機門開啟" } 
                8  { $errDetails = "8: 夾紙" } 
                9  { $errDetails = "9: 離線" }
                default { $errDetails = "硬體異常碼: $($p.DetectedErrorState)" }
            }
        } else {
            switch ($p.PrinterStatus) {
                1 { $finalStatus = "Error"; $errDetails = "驅驚異常" } 
                2 { $finalStatus = "Error"; $errDetails = "其他錯誤" }
                4 { $finalStatus = "Printing" } 
                5 { $finalStatus = "Warmup" }
                default { 
                    $finalStatus = "Ready"
                    if ($p.PrinterStatus -ne 3) { $finalStatus = "Warning" } 
                }
            }
        }
        
        $pIP = if ($portMap.ContainsKey($p.PortName)) { $portMap[$p.PortName] } else { $p.PortName }
        if ($pIP -match "^\d+\.\d+\.\d+\.\d+$") {
            if (-not (Test-TcpConnection $pIP 9100 200)) {
                if ($finalStatus -eq "Offline") { 
                    $errDetails = "無回應 (TCP)" 
                } elseif ($finalStatus -like "Ready*") { 
                    $finalStatus = "Warning"
                    $errDetails = "無回應 - 可能斷線" 
                }
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
    
    $global:KnownPrinters = $tempKnownPrinters.ToArray()
    return $results
}

function Test-PrinterHealth {
    Cleanup-OldLogs
    $printers = Get-PrinterStatusData
    $batchAlerts = New-Object System.Collections.Generic.List[string]
    $stuck = 0
    
    # [BIG5 完美解法] 動態生成警告標誌
    $eWarn = [char]::ConvertFromUtf32(0x26A0)
    
    if ($enableAutoCleanup) {
        $zombies = Get-WmiObject Win32_PrintJob | Where-Object { $_.JobStatus -like "*Error*" -or $_.JobStatus -like "*Deleting*" }
        if ($zombies) { 
            foreach ($z in $zombies) { 
                $batchAlerts.Add("自癒清理: $($z.JobId)")
                $z.Delete() 
            } 
        }
    }

    foreach ($p in $printers) {
        $n = $p.Name
        $s = $p.Status
        $j = $p.Jobs
        
        if ($global:IsFirstRun) { 
            if ($s -like "Offline*") { $global:ExcludedPrinters[$n] = $true }
            $global:LastQueueCount[$n] = $j
            continue 
        }
        
        if ($global:ExcludedPrinters.ContainsKey($n)) { 
            if ($s -eq "Ready" -or $s -eq "Printing") { $global:ExcludedPrinters.Remove($n) }
            continue 
        }
        
        if ($s -eq "Error" -or $s -eq "Warning") {
            $global:PrinterErrorCount[$n]++
            if ($global:PrinterErrorCount[$n] -eq $errorThreshold) { 
                $batchAlerts.Add("● [異常] $n $s") 
            }
        } else {
            if ($global:PrinterErrorCount[$n] -ge $errorThreshold) { 
                $batchAlerts.Add("○ [恢復] $n") 
            }
            $global:PrinterErrorCount[$n] = 0
        }
        
        if ($j -ge $queueThreshold -and $j -ge $global:LastQueueCount[$n]) {
            $global:QueueStuckCount[$n]++
            if ($global:QueueStuckCount[$n] -eq $queueStuckLimit) { 
                $batchAlerts.Add("$eWarn [堵塞] $n 佇列停滯")
                $stuck++ 
            }
        } else { 
            $global:QueueStuckCount[$n] = 0 
        }
        $global:LastQueueCount[$n] = $j
    }

    if ($enableAutoHeal -and $stuck -ge $maxStuckPrinters) { 
        Invoke-SpoolerSelfHealing -reason "多台堵塞" 
    }
    
    if ($global:IsFirstRun) { 
        $global:IsFirstRun = $false
        return 
    }
    
    if ($batchAlerts.Count -gt 0 -and $enableAdminNotifications) { 
        Send-SysAdminNotify -content ([string]::Join("`n", $batchAlerts)) -title "印表機告警" 
    }
}

# -------------------------------------------------------------------------
# 3. 主程序 (HttpListener & WebSocket & 即時監控)
# -------------------------------------------------------------------------
Write-ApiLog "----------------------------------------" -Color Cyan
Write-ApiLog " Print Server API & Monitor v17.68 " -Color Cyan
Write-ApiLog "----------------------------------------" -Color Cyan

# 啟動時設定防火牆
Setup-FirewallRule $port

# 偵測 PDF 閱讀器
foreach ($path in $pdfReaderPaths) { 
    if (Test-Path $path) { 
        $global:ValidPdfReader = $path
        break 
    } 
}

if ($global:ValidPdfReader) { 
    Write-ApiLog "PDF Reader: OK ($global:ValidPdfReader)" -Color Green 
    try { 
        Unblock-File -Path $global:ValidPdfReader -ErrorAction SilentlyContinue 
    } catch {}
} else { 
    Write-ApiLog "PDF Reader: Not Found" -Color Yellow 
}

$global:IsSumatraPDF = ($global:ValidPdfReader -match "SumatraPDF")
if ($global:IsSumatraPDF) { 
    Write-ApiLog ">>> [系統] 偵測到 SumatraPDF 引擎" -Color Magenta 
}

if ($enableDriveMapping -and -not [string]::IsNullOrEmpty($networkUsername)) {
    try { 
        net use "$mappedDriveUncPath" /delete /y 2>&1 | Out-Null 
    } catch {}
    
    try {
        $cmd = "net use `"$mappedDriveUncPath`" `"$networkPassword`" /user:`"$networkUsername`" /persistent:no"
        Invoke-Expression $cmd 2>&1 | Out-Null
    } catch {}
}

# 清除所有舊的背景事件訂閱
Get-EventSubscriber | Unregister-Event -ErrorAction SilentlyContinue

# 啟動 PrintLog 監控
try {
    if (Test-Path (Split-Path $printLogFilePath)) {
        $watcher = New-Object System.IO.FileSystemWatcher((Split-Path $printLogFilePath), (Split-Path $printLogFilePath -Leaf))
        $watcher.NotifyFilter = "LastWrite,Size"
        $watcher.EnableRaisingEvents = $true
        Register-ObjectEvent $watcher "Changed" -Action { $global:PrintLogDirty = $true } | Out-Null
        Register-ObjectEvent $watcher "Created" -Action { $global:PrintLogDirty = $true } | Out-Null
        Register-ObjectEvent $watcher "Renamed" -Action { $global:PrintLogDirty = $true } | Out-Null
        Write-ApiLog ">>> [System] PrintLog 檔案監控已啟動" -Color Magenta
    }
} catch {
    Write-ApiLog "!!! [System] PrintLog 監控啟動失敗: $($_.Exception.Message)" -Color Yellow
}

# 啟動 Spooler 佇列監控
try {
    if (Test-Path $spoolerPath) {
        $sWatcher = New-Object System.IO.FileSystemWatcher($spoolerPath, "*.*")
        $sWatcher.EnableRaisingEvents = $true
        Register-ObjectEvent $sWatcher "Created" -Action { $global:SpoolerDirty = $true } | Out-Null
        Register-ObjectEvent $sWatcher "Deleted" -Action { $global:SpoolerDirty = $true } | Out-Null
        Write-ApiLog ">>> [System] Spooler 佇列監控已啟動" -Color Magenta
    }
} catch {
    Write-ApiLog "!!! [System] Spooler 監控啟動失敗" -Color Yellow
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
$started = $false
$retryCount = 0

while (-not $started -and $retryCount -lt 5) {
    try { 
        $listener.Start()
        $started = $true
        Write-ApiLog ">>> 服務啟動於 Port $port (支援 WebSocket 安全認證)" -Color Green
    } catch { 
        $retryCount++
        Start-Sleep -Seconds 2 
    }
}

if (-not $started) { 
    Write-ApiLog "[嚴重錯誤] 服務啟動失敗" -Color Red
    exit 
}

Write-ApiLog ">>> [系統初始化] 快取暖機中..." -Color Cyan
Update-PrintLogCache
$global:PrintLogDirty = $false

$nextCheck = Get-Date
$nextHeart = Get-Date
$contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        
        # 定期記錄存活狀態
        if ($now -ge $nextHeart) { 
            Write-ApiLog "[Heartbeat] 服務運作中 (WS客戶端: $($global:WSClients.Count))..." -Color DarkGray
            $nextHeart = $now.AddSeconds(60) 
        } 
        
        # 處理 PrintLog 變更
        if ($global:PrintLogDirty) { 
            $global:PrintLogDirty = $false
            Start-Sleep -Milliseconds 200
            Update-PrintLogCache 
        }
        
        # 處理 Spooler 佇列即時推播 (極速 Win32_PrintJob 模式)
        if ($global:SpoolerDirty -and ($now - $global:LastSpoolerPushTime).TotalMilliseconds -ge 500) {
            $global:SpoolerDirty = $false
            $global:LastSpoolerPushTime = $now
            try {
                $jobCounts = @{}
                if ($global:KnownPrinters) {
                    foreach ($kp in $global:KnownPrinters) { $jobCounts[$kp] = 0 }
                }
                
                $jobs = Get-WmiObject -Class Win32_PrintJob -ErrorAction SilentlyContinue
                if ($jobs) {
                    foreach ($j in @($jobs)) {
                        $pName = $j.Name -replace ', \d+$', ''
                        if ($jobCounts.ContainsKey($pName)) {
                            $jobCounts[$pName]++
                        } else {
                            $jobCounts[$pName] = 1
                        }
                    }
                }
                
                $jsonBuilder = New-Object System.Text.StringBuilder
                [void]$jsonBuilder.Append("{`"type`":`"PRINTER_QUEUE_UPDATE`",`"data`":[")
                $first = $true
                
                foreach ($key in $jobCounts.Keys) { 
                    if (-not $first) { [void]$jsonBuilder.Append(",") }
                    $first = $false
                    $safeName = $key.Replace('\','\\').Replace('"','\"')
                    [void]$jsonBuilder.Append("{`"Name`":`"$safeName`",`"Jobs`":$($jobCounts[$key])}") 
                }
                [void]$jsonBuilder.Append("]}")
                
                Broadcast-WebSocketMessage $jsonBuilder.ToString()
            } catch {
                Write-ApiLog "!!! [Spooler] 佇列推播處理失敗: $($_.Exception.Message)" -Color Yellow
            }
        }

        # 定期健康檢查
        if ($now -ge $nextCheck) {
            $day = $now.DayOfWeek.ToString()
            if (($now.Hour -ge $monitorStartHour) -and ($now.Hour -lt $monitorEndHour) -and ($monitorDays -contains $day)) { 
                Test-PrinterHealth 
            }
            $nextCheck = $now.AddSeconds($checkIntervalSec)
        }

        # 定期深度自癒 (加入跨日邏輯判定)
        if ($enableScheduledHeal) {
            $currentMinStr = $now.ToString("yyyyMMddHHmm")
            if ($global:LastCronRunTime -ne $currentMinStr) {
                if (Test-CronMatch $scheduledHealCron $now) {
                    Invoke-SpoolerSelfHealing -reason "Cron 排程"
                    Rotate-PrintLog
                    $global:LastCronRunTime = $currentMinStr
                }
            }
        }

        # 處理 HTTP 請求
        if ($null -eq $contextTask) { 
            $contextTask = $listener.BeginGetContext($null, $null) 
        }
        
        if (-not $contextTask.AsyncWaitHandle.WaitOne(250)) { 
            continue 
        }
        
        $context = $listener.EndGetContext($contextTask)
        $contextTask = $null
        $req = $context.Request
        $res = $context.Response
        $path = $req.Url.AbsolutePath.ToLower()
        $pendingAction = $null  # [重要] 每次請求重置延遲動作標記

        # [核心] WebSocket 安全認證
        if ($req.IsWebSocketRequest) {
            $wsKey = $req.QueryString["key"]
            if ($wsKey -ne $apiKey) {
                Write-ApiLog "!!! [SECURITY] WebSocket 認證失敗: 錯誤金鑰來自 $($req.RemoteEndPoint)" -Color Red
                $res.StatusCode = 401
                $res.Close()
                continue
            }
            try {
                $wsContext = $context.AcceptWebSocketAsync([NullString]::Value).GetAwaiter().GetResult()
                [void]$global:WSClients.Add($wsContext.WebSocket)
                Write-ApiLog ">>> [WebSocket] 認證通過，網頁端已連線" -Color Magenta
            } catch { 
                $res.StatusCode = 500
                $res.Close() 
            }
            continue
        }

        # HTTP API 處理
        Write-ApiLog ">>> [REQ] $($req.RemoteEndPoint) $path"
        $res.AddHeader("Access-Control-Allow-Origin", "*")
        $res.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $res.AddHeader("Access-Control-Allow-Headers", "*") 
        
        if ($req.HttpMethod -eq "OPTIONS") {
            $res.StatusCode = 200
            $res.Close()
            continue
        }
        
        $out = @{ "success"=$false; "message"=""; "data"=$null }
        $handledBinary = $false
        
        if ($req.Headers["X-API-KEY"] -ne $apiKey) { 
            $res.StatusCode = 401 
        } else {
            # 1. 取得所有印表機清單
            if ($path -eq "/printers") { 
                $out.data = Get-PrinterStatusData
                $out.success = $true
                $out.isLandSystem = (Test-Path $printLogFilePath -PathType Leaf) 
            }
            # 2. 取得單一印表機狀態
            elseif ($path -eq "/printer/status") {
                $n = Get-Utf8QueryParam $req "name"
                $data = Get-PrinterStatusData
                foreach ($i in $data) {
                    if ($i.Name -eq $n) {
                        $out.data = $i
                        $out.success = $true
                        break
                    }
                }
            }
            # 3. 刷新印表機
            elseif ($path -eq "/printer/refresh") {
                $n = Get-Utf8QueryParam $req "name"
                $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                if ($p) { 
                    $p.Pause()
                    Start-Sleep -m 500
                    $p.Resume()
                    $out.success = $true 
                }
            }
            # 4. 更新印表機備註與位置
            elseif ($path -eq "/printer/update") {
                $n = Get-Utf8QueryParam $req "name"
                $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                if ($p) { 
                    $l = Get-Utf8QueryParam $req "location"
                    $c = Get-Utf8QueryParam $req "comment"
                    if ($l) { $p.Location = $l }
                    if ($c) { $p.Comment = $c }
                    try {
                        $p.Put()
                        $out.success = $true
                    } catch {} 
                }
            }
            # 5. 取得伺服器日誌
            elseif ($path -eq "/server/logs") { 
                $logF = Join-Path $logPath "PrintApi_$((Get-Date).ToString('yyyy-MM-dd')).log"
                if (Test-Path $logF) {
                    $cnt = 100
                    $l = Get-Utf8QueryParam $req "lines"
                    if ($l -match "^\d+$") { $cnt = [int]$l }
                    $out.data = Get-Content $logF | Select-Object -Last $cnt
                    $out.success = $true 
                } else {
                    $out.message = "No Log"
                }
            }
            # 6. 取得列印紀錄
            elseif ($path -eq "/server/printlog") {
                $logData = @(Get-PrintLogs)
                $out.data = $logData
                $out.success = $true
            }
            # 7. 取得表單目錄
            elseif ($path -eq "/server/applyforms") {
                $tDir = Join-Path $mappedDriveUncPath "temp"
                if (Test-Path $tDir) { 
                    $filter = Get-Utf8QueryParam $req "filter"
                    if ([string]::IsNullOrEmpty($filter)) { $filter = "cer_ApplyForm_*.pdf" }
                    
                    $files = Get-ChildItem $tDir -Filter $filter -ErrorAction SilentlyContinue | Where-Object {$_.LastWriteTime.Date -eq (Get-Date).Date} | Sort-Object LastWriteTime -Descending
                    $arr = New-Object System.Collections.ArrayList
                    
                    foreach ($f in $files) { 
                        [void]$arr.Add(@{
                            name = $f.Name
                            path = ($f.FullName -replace '\\', '/')
                            time = $f.LastWriteTime.ToString("HH:mm:ss")
                            size = ("{0:N2} KB" -f ($f.Length/1KB))
                        }) 
                    }
                    $out.data = $arr.ToArray()
                    $out.success = $true
                    $out.message = "成功取得 $($arr.Count) 筆表單" 
                } else {
                    $out.message = "找不到表單目錄"
                }
            }
            # 8. 重印紀錄檔案 (Re-print)
            elseif ($path -eq "/printer/re-print") {
                $n = Get-Utf8QueryParam $req "name"
                $rawPath = Get-Utf8QueryParam $req "path"
                $dup = Get-Utf8QueryParam $req "duplex"
                $fPath = Resolve-VirtualPath $rawPath
                
                $isAuthorized = $false
                if (-not [string]::IsNullOrEmpty($rawPath)) {
                    $normRaw = $rawPath.Replace("/", "\")
                    foreach ($log in @(Get-PrintLogs)) { 
                        if ($log.path.Replace("/", "\") -eq $normRaw) { 
                            $isAuthorized = $true
                            break 
                        } 
                    }
                    if (-not $isAuthorized -and $enableDriveMapping) { 
                        if ($fPath.StartsWith($mappedDriveUncPath, [System.StringComparison]::OrdinalIgnoreCase)) { 
                            $isAuthorized = $true 
                        } 
                    }
                }

                if (-not $isAuthorized) { 
                    $out.success = $false
                    $out.message = "拒絕重印未授權檔案"
                    $res.StatusCode = 403 
                } elseif ([string]::IsNullOrEmpty($n) -or [string]::IsNullOrEmpty($fPath)) { 
                    $out.message = "缺少參數" 
                } else {
                    $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                    if ($p) {
                        if (-not (Test-Path $fPath -PathType Leaf)) { 
                            $out.message = "檔案不存在" 
                        } else {
                            $restoreDup = $false
                            $oldDup = $null
                            if ($dup -and $dup -ne "0" -and -not $global:IsSumatraPDF) {
                                try {
                                    $cfg = Get-PrintConfiguration -PrinterName $n -ErrorAction Stop
                                    $oldDup = $cfg.DuplexingMode
                                    $tDup = "OneSided"
                                    if ($dup -eq "1" -or $dup -eq "long") { 
                                        $tDup = "TwoSidedLongEdge" 
                                    } elseif ($dup -eq "2" -or $dup -eq "short") { 
                                        $tDup = "TwoSidedShortEdge" 
                                    }
                                    if ($oldDup -ne $tDup) { 
                                        Set-PrintConfiguration -PrinterName $n -DuplexingMode $tDup -ErrorAction Stop
                                        $restoreDup = $true 
                                    }
                                } catch {}
                            }

                            try {
                                if ($global:ValidPdfReader) {
                                    if ($global:IsSumatraPDF) {
                                        $sumatraSettings = ""
                                        if ($dup -eq "1") { 
                                            $sumatraSettings = "duplexlong" 
                                        } elseif ($dup -eq "2") { 
                                            $sumatraSettings = "duplexshort" 
                                        }
                                        if ($sumatraSettings) {
                                            $argList = "-print-to `"$n`" -print-settings `"$sumatraSettings`" `"$fPath`""
                                        } else {
                                            $argList = "-print-to `"$n`" `"$fPath`""
                                        }
                                        Start-Process -FilePath $global:ValidPdfReader -ArgumentList $argList -WindowStyle Hidden
                                    } else {
                                        Start-Process -FilePath $global:ValidPdfReader -ArgumentList "/t `"$fPath`" `"$n`"" -WindowStyle Hidden
                                    }
                                } else { 
                                    Start-Process -FilePath $fPath -Verb PrintTo -ArgumentList "`"$n`"" -WindowStyle Hidden 
                                }
                                $out.success = $true
                                $out.message = "已發送指令"
                            } catch { 
                                $out.message = $_.Exception.Message 
                            }
                            
                            if ($restoreDup) { 
                                Start-Sleep -Seconds 5
                                try { Set-PrintConfiguration -PrinterName $n -DuplexingMode $oldDup } catch {} 
                            }
                        }
                    } else { 
                        $out.message = "找不到印表機" 
                    }
                }
            }
            # 9. 預覽紀錄檔案 (Preview)
            elseif ($path -eq "/printer/preview") {
                $rawPath = Get-Utf8QueryParam $req "path"
                $fPath = Resolve-VirtualPath $rawPath
                $isAuthorized = $false
                if (-not [string]::IsNullOrEmpty($rawPath)) {
                    $normRaw = $rawPath.Replace("/", "\")
                    foreach ($log in @(Get-PrintLogs)) { 
                        if ($log.path.Replace("/", "\") -eq $normRaw) { 
                            $isAuthorized = $true
                            break 
                        } 
                    }
                    if (-not $isAuthorized -and $enableDriveMapping) { 
                        if ($fPath.StartsWith($mappedDriveUncPath, [System.StringComparison]::OrdinalIgnoreCase)) { 
                            $isAuthorized = $true 
                        } 
                    }
                }
                
                if (-not $isAuthorized) { 
                    $res.StatusCode = 403 
                } elseif (Test-Path $fPath -PathType Leaf) {
                    try {
                        $bytes = [System.IO.File]::ReadAllBytes($fPath)
                        $res.ContentType = "application/pdf"
                        $res.AddHeader("Content-Disposition", "inline; filename=`"preview.pdf`"")
                        $res.AddHeader("Access-Control-Expose-Headers", "Content-Disposition")
                        $res.ContentLength64 = $bytes.Length
                        $res.OutputStream.Write($bytes, 0, $bytes.Length)
                        $res.Close()
                        $handledBinary = $true
                    } catch {}
                }
            }
            # 10. 上傳並列印 PDF
            elseif ($path -eq "/printer/print-pdf") {
                if ($req.HttpMethod -eq "POST") {
                    $n = Get-Utf8QueryParam $req "name"
                    $dup = Get-Utf8QueryParam $req "duplex"
                    $p = Get-WmiObject Win32_Printer | Where {$_.Name -eq $n}
                    
                    if ($p) {
                         $restoreDup = $false
                         $oldDup = $null
                         
                         if ($dup -and $dup -ne "0" -and -not $global:IsSumatraPDF) {
                            try {
                                $cfg = Get-PrintConfiguration -PrinterName $n -ErrorAction Stop
                                $oldDup = $cfg.DuplexingMode
                                $tDup = if($dup -eq "1"){"TwoSidedLongEdge"}elseif($dup -eq "2"){"TwoSidedShortEdge"}else{"OneSided"}
                                if ($oldDup -ne $tDup) { 
                                    Set-PrintConfiguration -PrinterName $n -DuplexingMode $tDup -ErrorAction Stop
                                    $restoreDup = $true 
                                }
                            } catch {}
                         }
                         
                         $fPath = Join-Path $uploadPath "Upload_$(Get-Date -Format 'yyyyMMdd_HHmmss').pdf"
                         $fs = New-Object System.IO.FileStream($fPath, [System.IO.FileMode]::Create)
                         $buf = New-Object byte[] 8192
                         
                         do { 
                             $r = $req.InputStream.Read($buf,0,$buf.Length)
                             if ($r -gt 0) { $fs.Write($buf,0,$r) } 
                         } while ($r -gt 0)
                         
                         $fs.Close()
                         
                         try {
                             if ($global:ValidPdfReader) {
                                 if ($global:IsSumatraPDF) {
                                     $st = if($dup -eq "1"){"duplexlong"}elseif($dup -eq "2"){"duplexshort"}else{""}
                                     $arg = if($st){"-print-to `"$n`" -print-settings `"$st`" `"$fPath`""}else{"-print-to `"$n`" `"$fPath`""}
                                     Start-Process -FilePath $global:ValidPdfReader -ArgumentList $arg -WindowStyle Hidden
                                 } else { 
                                     Start-Process -FilePath $global:ValidPdfReader -ArgumentList "/t `"$fPath`" `"$n`"" -WindowStyle Hidden 
                                 }
                             } else { 
                                 Start-Process -FilePath $fPath -Verb PrintTo -ArgumentList "`"$n`"" -WindowStyle Hidden 
                             }
                             $out.success = $true
                         } catch {
                             $out.message = $_.Exception.Message
                         }
                         
                         if ($restoreDup) { 
                             Start-Sleep -Seconds 5
                             try { Set-PrintConfiguration -PrinterName $n -DuplexingMode $oldDup } catch {} 
                         }
                    }
                }
            }
            # 11. 清空單一佇列
            elseif ($path -eq "/printer/clear") {
                $n = Get-Utf8QueryParam $req "name"
                $js = Get-WmiObject Win32_PrintJob | Where {$_.Name -like "*$n*"}
                if ($js) { 
                    foreach ($j in $js) { $j.Delete() } 
                }
                $out.success = $true
            }
            # 12. 重啟 Spooler 服務 [已修改: 延遲執行防阻塞]
            elseif ($path -eq "/service/restart-spooler") { 
                $out.success = $true 
                $out.message = "正在背景執行重啟 Spooler 服務..."
                $pendingAction = "restart-spooler"
            }
            # 13. 深度自癒 [已修改: 延遲執行防阻塞]
            elseif ($path -eq "/service/self-heal") { 
                $out.success = $true 
                $out.message = "正在背景執行深度自癒流程..."
                $pendingAction = "self-heal"
            }
            # 14. 重啟腳本
            elseif ($path -eq "/server/restart-script") { 
                $out.success = $true
                $restartScript = $true 
            }
            # 15. 伺服器重新開機
            elseif ($path -eq "/server/restart-computer") { 
                $out.success = $true
                $restartComputer = $true 
            }
            else { 
                $res.StatusCode = 404 
            }
        }

        # 輸出 JSON (如果不是預覽 PDF 的二進位檔)
        # 此段會關閉網路連線，將結果送回前端
        if (-not $handledBinary) {
            $json = ConvertTo-SimpleJson $out
            $buf = [System.Text.Encoding]::UTF8.GetBytes($json)
            $res.ContentType = "application/json"
            try {
                $res.ContentLength64 = $buf.Length
                $res.OutputStream.Write($buf, 0, $buf.Length)
                $res.Close()
            } catch {}
        }

        # ---------------------------------------------------------
        # [核心解法] 執行延遲動作 (確保前端先收到成功回應，不再 Timeout)
        # ---------------------------------------------------------
        if ($pendingAction -eq "restart-spooler") {
            Write-ApiLog ">>> [系統操作] 正在執行延遲動作: 重啟 Spooler 服務" -Color Yellow
            try { 
                # [防卡死優化] 強制停止，並在背景確保進程被獵殺
                Stop-Service -Name "Spooler" -Force -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
                Start-Sleep -Seconds 2
                $spoolerProc = Get-Process -Name "spoolsv" -ErrorAction SilentlyContinue
                if ($spoolerProc) { $spoolerProc | Stop-Process -Force -ErrorAction SilentlyContinue }
                Start-Sleep -Seconds 1
                Start-Service -Name "Spooler"
                Write-ApiLog ">>> [系統操作] Spooler 服務已成功重啟" -Color Green
            } catch {
                Write-ApiLog "!!! [系統操作] Spooler 重啟失敗: $($_.Exception.Message)" -Color Red
            }
        } elseif ($pendingAction -eq "self-heal") {
            Write-ApiLog ">>> [系統操作] 正在執行延遲動作: 深度自癒" -Color Yellow
            Invoke-SpoolerSelfHealing "API Trigger"
        }

        # 執行重啟動作
        if ($restartScript) { 
            try { $listener.Stop(); $listener.Close() } catch {}
            Start-Process powershell -ArgumentList "-ExecutionPolicy Bypass -File `"$($MyInvocation.MyCommand.Definition)`"" -WindowStyle Hidden
            exit 
        }
        if ($restartComputer) {
            try { $listener.Stop(); $listener.Close() } catch {}
            if ($enableAdminNotifications) { 
                Send-SysAdminNotify -content "API：收到管理員指令，伺服器即將在 5 秒後重新啟動。" -title "系統操作" 
            }
            Start-Process "shutdown.exe" -ArgumentList "/r /t 5 /f /d p:4:1"
            exit
        }

    } catch { 
        $contextTask = $null 
    }
}