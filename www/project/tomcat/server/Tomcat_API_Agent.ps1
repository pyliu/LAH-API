<#
.SYNOPSIS
    資深系統整合工程師實作版本 - Tomcat Server HTTP API Agent
    版本：v1.6 (純淨排版修復版 - 徹底解決隱形字元與解析錯誤)
    
.DESCRIPTION
    本腳本提供 Apache Tomcat 的遠端 API 管理能力，具備以下核心功能：
    1. [環境解耦] 透過 Tomcat_API_Agent.env 外部設定檔管理所有變數。
    2. [服務管理] 支援對「Apache Tomcat 7.0 Tomcat7」服務進行狀態查詢與重啟。
    3. [日誌支援] 自動偵測 BIG5 (ANSI) 編碼，解決 catalina 運行日誌中文亂碼。
    4. [排程維護] 內建 Cron 機制，預設每天早上 07:30 自動停機、清快取、重啟。
    5. [強化重啟] 使用 Abort() 與 [Environment]::Exit(0) 解決 HTTP 併發卡死問題。
    6. [終極防佔用] 具備 Process Tree 級別的 Port 強殺能力，保證服務重啟成功。
#>

# -------------------------------------------------------------------------
# 1. 基礎設定區
# -------------------------------------------------------------------------

$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } elseif ($MyInvocation.MyCommand.Path) { Split-Path $MyInvocation.MyCommand.Path } else { $PWD.Path }
$envFile = Join-Path $scriptDir "Tomcat_API_Agent.env"
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
        Write-Host ">>> [系統] 已成功載入外部設定檔: Tomcat_API_Agent.env" -ForegroundColor Cyan
    } catch { 
        Write-Host "!!! [系統] 讀取設定檔失敗，降級使用內建預設值" -ForegroundColor Yellow 
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
    if ($envConfig.Contains($key) -and $envConfig[$key] -ne '') { return @($envConfig[$key] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }) } 
    return $default 
}

# --- 變數映射 ---
$port               = Get-EnvInt "PORT" 8888
$apiKey             = Get-EnvString "API_KEY" "TomcatSecretKey123!"      
$logPath            = Get-EnvString "LOG_PATH" "C:\Temp\TomcatApiLogs"
$maxLogSizeBytes    = Get-EnvInt "MAX_LOG_SIZE_BYTES" 10485760 # 10MB                       
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
$notifyChannels     = Get-EnvArray "NOTIFY_CHANNELS" @("HA10013859")

$enableNotifyHealthCheck  = Get-EnvBool "ENABLE_NOTIFY_HEALTH_CHECK" $true
$notifyTimeoutMs          = Get-EnvInt "NOTIFY_TIMEOUT_MS" 1000
$enableAdminNotifications = Get-EnvBool "ENABLE_ADMIN_NOTIFICATIONS" $true    

# 全局狀態
$global:IsNotifyServerOnline = $true 
$global:LastCronRunTime = $null 
$restartScript = $false
$restartComputer = $false

# -------------------------------------------------------------------------
# 2. 核心函數庫
# -------------------------------------------------------------------------

function ConvertTo-SimpleJson {
    param($InputObject)
    
    if ($null -eq $InputObject) { 
        return "null" 
    }
    
    if ($InputObject -is [string]) { 
        return """$($InputObject.Replace('\', '\\').Replace('"', '\"').Replace("`n", "\n").Replace("`r", "\r").Replace("`t", "\t"))""" 
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
        $fullPath = Join-Path $logPath "TomcatApi_$today.log"
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
        
        if (Test-Path $fullPath) {
            $fileItem = Get-Item $fullPath -ErrorAction SilentlyContinue
            if ($null -ne $fileItem -and $fileItem.Length -ge $maxLogSizeBytes) {
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
        Write-Host $logEntry -ForegroundColor $Color
    } catch {}
}

function Cleanup-OldLogs {
    try {
        if (Test-Path $logPath) {
            $limitDate = (Get-Date).AddDays(-$logRetentionDays)
            $oldFiles = Get-ChildItem -Path $logPath -Filter "TomcatApi_*.log*" | Where-Object { $_.LastWriteTime -lt $limitDate }
            if ($oldFiles) { 
                foreach ($f in $oldFiles) { 
                    Remove-Item $f.FullName -Force 
                } 
            }
        }
    } catch {}
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
        $tcp.Close() 
    }
}

function Send-SysAdminNotify {
    param([string]$content, [string]$title = "Tomcat 系統通知")
    if ($null -eq $notifyChannels -or $notifyChannels.Count -eq 0 -or ($enableNotifyHealthCheck -and -not $global:IsNotifyServerOnline)) { 
        return 
    }
    
    try {
        $localIp = "127.0.0.1"
        try { 
            $ip = Get-WmiObject Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled }
            if ($ip -is [array]) { 
                $localIp = $ip[0].IPAddress[0] 
            } else { 
                $localIp = $ip.IPAddress[0] 
            } 
        } catch {}
        
        $fields = @{ 
            "type"="add_notification"
            "title"=$title
            "content"=$content
            "priority"="3"
            "sender"="$($env:COMPUTERNAME) ($localIp)"
            "from_ip"=$localIp 
        }
        
        $body = New-Object System.Collections.Generic.List[string]
        foreach ($k in $fields.Keys) { 
            $body.Add("$k=$([System.Uri]::EscapeDataString($fields[$k]))") 
        }
        foreach ($c in $notifyChannels) { 
            $body.Add("channels[]=$([System.Uri]::EscapeDataString($c))") 
        }
        
        $postData = [System.Text.Encoding]::UTF8.GetBytes([string]::Join("&", $body))
        $req = [System.Net.WebRequest]::Create($notifyUrl)
        $req.Method = "POST"
        $req.ContentType = "application/x-www-form-urlencoded"
        $req.Timeout = 2000 
        $reqStream = $req.GetRequestStream()
        $reqStream.Write($postData, 0, $postData.Length)
        $reqStream.Close()
        $req.GetResponse().Close()
    } catch { 
        Write-ApiLog "!!! 通知發送失敗: $($_.Exception.Message)" 
    }
}

function Get-Utf8QueryParam { 
    param($request, $key)
    if ($request.Url.Query -match "[?&]$key=([^&]*)") {
        try { 
            return [System.Uri]::UnescapeDataString($matches[1].Replace("+", "%20")) 
        } catch { 
            return $null 
        }
    }
    return $null
}

function Test-CronMatch {
    param($cron, $now)
    if ([string]::IsNullOrEmpty($cron)) { return $false }
    $parts = $cron.Split(" ")
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
            foreach($i in $p.Split(",")) { 
                if ([int]$i -eq $v) { return $true } 
            } 
            return $false 
        }
        return [int]$p -eq $v
    }
    
    return (Check $min $now.Minute) -and (Check $hour $now.Hour) -and (Check $dom $now.Day) -and (Check $month $now.Month) -and (Check $dow [int]$now.DayOfWeek)
}

function Invoke-ScheduledTomcatRestart {
    Write-ApiLog ">>> [排程任務] 觸發定時維護 (清理快取與重啟服務)..." -Color Yellow
    if ($enableAdminNotifications) { 
        Send-SysAdminNotify -title "Tomcat 排程維護" -content "開始執行每日定時清理快取與重啟服務..." 
    }
    
    try {
        $svc = Get-Service | Where-Object { $_.Name -eq $tomcatServiceName -or $_.DisplayName -eq $tomcatServiceName } | Select-Object -First 1
        if (-not $svc) { 
            throw "找不到服務: $tomcatServiceName" 
        }
        
        Write-ApiLog " -> 正在停止 Tomcat 服務..."
        Stop-Service -Name $svc.Name -Force -ErrorAction Stop
        Start-Sleep -Seconds 5
        
        Write-ApiLog " -> 正在清理 work 與 temp 目錄..."
        $w = Join-Path $tomcatDir "work"
        $t = Join-Path $tomcatDir "temp"
        
        if (Test-Path $w) { 
            Get-ChildItem -Path $w -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue 
        }
        if (Test-Path $t) { 
            Get-ChildItem -Path $t -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue 
        }
        
        Write-ApiLog " -> 正在啟動 Tomcat 服務..."
        Start-Service -Name $svc.Name -ErrorAction Stop
        
        Write-ApiLog ">>> [排程任務] Tomcat 維護完成！" -Color Green
        if ($enableAdminNotifications) { 
            Send-SysAdminNotify -title "Tomcat 排程維護" -content "每日定時清理與重啟已順利完成。" 
        }
    } catch {
        Write-ApiLog "!!! [排程任務] 執行失敗: $($_.Exception.Message)" -Color Red
        if ($enableAdminNotifications) { 
            Send-SysAdminNotify -title "Tomcat 排程維護異常" -content "執行過程發生錯誤: $($_.Exception.Message)" 
        }
    }
}

# --- ?? 殭屍通訊埠終極獵殺函數 (Double Engine + Tree Kill) ---
function Clear-ZombiePort {
    param([int]$TargetPort)
    try {
        $pidsToKill = @()
        
        # 引擎 1：使用現代 PowerShell CMDLet 精準獲取 PID
        if (Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue) {
            $conns = Get-NetTCPConnection -LocalPort $TargetPort -State Listen -ErrorAction SilentlyContinue
            if ($conns) { 
                foreach ($c in $conns) { 
                    $pidsToKill += $c.OwningProcess 
                } 
            }
        }
        
        # 引擎 2：如果引擎 1 不支援，降級使用強化版 netstat 正則解析
        if ($pidsToKill.Count -eq 0) {
            $netstat = netstat -ano | Select-String "LISTENING" | Select-String ":$TargetPort\s+"
            if ($netstat) {
                foreach ($lineItem in $netstat) {
                    $parts = $lineItem.Line.Trim() -split '\s+'
                    $zombiePid = $parts[-1] 
                    if ($zombiePid -match "^\d+$") { 
                        $pidsToKill += [int]$zombiePid 
                    }
                }
            }
        }

        $pidsToKill = $pidsToKill | Select-Object -Unique
        
        foreach ($zPid in $pidsToKill) {
            if ($zPid -eq 4) {
                Write-ApiLog "!!! [致命警告] Port $TargetPort 被系統核心 (PID 4: HTTP.sys / System) 佔用。" -Color Red
                Write-ApiLog "!!! 系統拒絕強殺該服務。請變更 .env 中的 PORT，或檢查是否有 IIS 綁定了該 Port。" -Color Red
            }
            elseif ($zPid -ne $PID -and $zPid -gt 4) {
                $procName = "Unknown"
                try { 
                    $procName = (Get-Process -Id $zPid -ErrorAction SilentlyContinue).ProcessName 
                } catch {}
                
                Write-ApiLog ">>> 發現 Port $TargetPort 被 [$procName] (PID: $zPid) 佔用，執行終極清除..." -Color Yellow
                
                # 絕殺 1：TaskKill 包含 /T (砍除整個 Process Tree)
                cmd.exe /c "taskkill /F /PID $zPid /T" 2>&1 | Out-Null
                
                # 絕殺 2：PowerShell 原生強殺 (保險機制)
                Stop-Process -Id $zPid -Force -ErrorAction SilentlyContinue
                
                Start-Sleep -Seconds 2
            }
        }
    } catch {
        Write-ApiLog "!!! 清除佔用 Port 時發生異常: $($_.Exception.Message)" -Color Red
    }
}

# -------------------------------------------------------------------------
# 3. 主程序 (HttpListener)
# -------------------------------------------------------------------------
Write-ApiLog "----------------------------------------" -Color Cyan
Write-ApiLog " Tomcat API Agent v1.6 (Port: $port) " -Color Cyan
Write-ApiLog "----------------------------------------" -Color Cyan

Setup-FirewallRule $port

if ($enableNotifyHealthCheck) {
    if (Test-TcpConnection $notifyIp $notifyPort $notifyTimeoutMs) { 
        Write-ApiLog "通知伺服器: 連線正常" -Color Green 
    } else { 
        $global:IsNotifyServerOnline = $false
        Write-ApiLog "通知伺服器: 離線 (停用即時通知)" -Color Red 
    }
}

# 啟動前先獵殺殭屍程序
Clear-ZombiePort $port

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
$started = $false
$retryCount = 0

while (-not $started -and $retryCount -lt 5) {
    try {
        $listener.Start()
        $started = $true
        Write-ApiLog "API 服務已啟動，監聽 Port $port" -Color Green
    } catch {
        $retryCount++
        Write-ApiLog "!!! 無法綁定 Port $port (嘗試 $retryCount/5)，嘗試再次強殺佔用程序..." -Color Red
        Clear-ZombiePort $port
        Start-Sleep -Seconds 2
    }
}

if (-not $started) {
    Write-ApiLog "!!! 嚴重錯誤：無法啟動監聽，Port $port 被頑固服務佔用，程式將終止。" -Color Red
    [System.Environment]::Exit(1)
}

$nextCleanup = Get-Date
$contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        
        # 日誌輪替
        if ($now -ge $nextCleanup) { 
            Cleanup-OldLogs
            $nextCleanup = $now.AddHours(24) 
        }

        # Cron 排程檢測
        if ($enableScheduledRestart) {
            if ($global:LastCronRunTime -eq $null -or ($now.Minute -ne $global:LastCronRunTime.Minute -or $now.Hour -ne $global:LastCronRunTime.Hour)) {
                if (Test-CronMatch $scheduledRestartCron $now) {
                    Invoke-ScheduledTomcatRestart
                    $global:LastCronRunTime = $now
                }
            }
        }

        # API 請求處理
        if ($null -eq $contextTask) { 
            $contextTask = $listener.BeginGetContext($null, $null) 
        }
        
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { 
            continue 
        }

        $context = $listener.EndGetContext($contextTask)
        $contextTask = $null
        $req = $context.Request
        $res = $context.Response
        $path = $req.Url.AbsolutePath.ToLower()
        
        Write-ApiLog ">>> [請求] $($req.RemoteEndPoint) $path"

        $res.AddHeader("Access-Control-Allow-Origin", "*")
        $res.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $res.AddHeader("Access-Control-Allow-Headers", "*") 
        
        if ($req.HttpMethod -eq "OPTIONS") { 
            $res.StatusCode = 200
            $res.Close()
            continue 
        }

        $out = @{ "success"=$false; "message"=""; "data"=$null }

        # ?? 攤平路由判斷結構，徹底避免巢狀 if 帶來的解析問題
        if ($req.Headers["X-API-KEY"] -ne $apiKey) {
            $res.StatusCode = 401
            $out.message = "金鑰錯誤 (Unauthorized)"
        } 
        elseif ($path -eq "/server/restart-script") {
            $out.success = $true
            $out.message = "Agent 腳本重啟中..."
            $restartScript = $true
        } 
        elseif ($path -eq "/server/restart-computer") {
            $out.success = $true
            $out.message = "伺服器即將於 5 秒後重啟..."
            $restartComputer = $true
        } 
        elseif ($path -eq "/server/logs") {
            $logF = Join-Path $logPath "TomcatApi_$(Get-Date -Format 'yyyy-MM-dd').log"
            if (Test-Path $logF) {
                $cnt = 100
                $l = Get-Utf8QueryParam $req "lines"
                if ($l -match "^\d+$") { $cnt = [int]$l }
                $out.data = Get-Content $logF | Select-Object -Last $cnt
                $out.success = $true
            } else { 
                $out.message = "找不到今日 API 日誌" 
            }
        } 
        elseif ($path -eq "/tomcat/status") {
            try {
                $svc = Get-Service | Where-Object { $_.Name -eq $tomcatServiceName -or $_.DisplayName -eq $tomcatServiceName } | Select-Object -First 1
                if (-not $svc) { 
                    throw "找不到服務: $tomcatServiceName" 
                }
                $out.data = @{ 
                    "ServiceName"=$svc.Name; 
                    "DisplayName"=$svc.DisplayName; 
                    "Status"=$svc.Status.ToString(); 
                    "TomcatDir"=$tomcatDir 
                }
                $out.success = $true
                $out.message = "OK"
            } catch { 
                $out.message = $_.Exception.Message 
            }
        } 
        elseif ($path -eq "/tomcat/restart") {
            try {
                $svc = Get-Service | Where-Object { $_.Name -eq $tomcatServiceName -or $_.DisplayName -eq $tomcatServiceName } | Select-Object -First 1
                if (-not $svc) { 
                    throw "找不到服務: $tomcatServiceName" 
                }
                Write-ApiLog "執行重啟 Tomcat 服務..." -Color Yellow
                Restart-Service -Name $svc.Name -Force -ErrorAction Stop
                $out.success = $true
                $out.message = "服務重啟成功"
                if ($enableAdminNotifications) { 
                    Send-SysAdminNotify -title "Tomcat 重啟通知" -content "管理員已成功透過遠端 API 重啟 Tomcat 服務。" 
                }
            } catch { 
                $out.message = "重啟失敗: $($_.Exception.Message)" 
            }
        } 
        elseif ($path -eq "/tomcat/logs") {
            $logDate = Get-Date -Format "yyyy-MM-dd"
            $catalinaLog = Join-Path $tomcatDir "logs\catalina.$logDate.log"
            if (-not (Test-Path $catalinaLog)) { 
                $catalinaLog = Join-Path $tomcatDir "logs\catalina.out" 
            }
            if (Test-Path $catalinaLog) {
                $cnt = 200
                $l = Get-Utf8QueryParam $req "lines"
                if ($l -match "^\d+$") { $cnt = [int]$l }
                try {
                    $out.data = Get-Content $catalinaLog -Tail $cnt -Encoding Default -ErrorAction Stop
                    $out.success = $true
                    $out.message = "日誌讀取完成"
                } catch { 
                    $out.message = "讀取發生錯誤: $($_.Exception.Message)" 
                }
            } else { 
                $out.message = "找不到今日的 catalina 日誌" 
            }
        } 
        elseif ($path -eq "/tomcat/clean-cache") {
            try {
                $w = Join-Path $tomcatDir "work"
                $t = Join-Path $tomcatDir "temp"
                if (Test-Path $w) { 
                    Get-ChildItem -Path $w -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue 
                }
                if (Test-Path $t) { 
                    Get-ChildItem -Path $t -Recurse | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue 
                }
                $out.success = $true
                $out.message = "快取目錄 (work/temp) 已清空"
                Write-ApiLog "已執行 Tomcat 快取清理" -Color Cyan
            } catch { 
                $out.message = "清理失敗: $($_.Exception.Message)" 
            }
        } 
        elseif ($path -eq "/tomcat/clean-crash-dumps") {
            try {
                $dumps = Get-ChildItem -Path $tomcatDir -Filter "hs_err_pid*" -File
                if ($dumps) {
                    $sz = 0
                    foreach ($f in $dumps) { 
                        $sz += $f.Length
                        Remove-Item $f.FullName -Force 
                    }
                    $out.success = $true
                    $out.message = "已刪除 $($dumps.Count) 個 Dump 檔，釋放 $([math]::Round($sz/1MB,2)) MB 空間"
                    Write-ApiLog "清理了 $($dumps.Count) 個 JVM Crash Dumps" -Color Green
                } else { 
                    $out.success = $true
                    $out.message = "未發現 Dump 檔案" 
                }
            } catch { 
                $out.message = "刪除失敗: $($_.Exception.Message)" 
            }
        } 
        else { 
            $res.StatusCode = 404 
        }

        # JSON 輸出
        $json = ConvertTo-SimpleJson $out
        $buf = [System.Text.Encoding]::UTF8.GetBytes($json)
        $res.ContentType = "application/json"
        $res.ContentLength64 = $buf.Length
        
        try { 
            $res.OutputStream.Write($buf, 0, $buf.Length)
            $res.Close() 
        } catch {
            # 忽略 Client 端強制中斷
        }

        # ==========================================
        # ?? 強化版：程序重啟與關機邏輯
        # ==========================================
        if ($restartScript) {
            Write-ApiLog ">>> 準備重新啟動 Agent..." -Color Yellow
            try { 
                $listener.Abort() 
            } catch {} 
            
            $scriptPath = $PSCommandPath
            if ([string]::IsNullOrEmpty($scriptPath)) { 
                $scriptPath = $MyInvocation.MyCommand.Definition 
            }
            
            Start-Process powershell.exe -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`""
            [System.Environment]::Exit(0)
        }
        
        if ($restartComputer) {
            Write-ApiLog ">>> 伺服器即將關機重啟..." -Color Red
            try { 
                $listener.Abort() 
            } catch {}
            
            if ($enableAdminNotifications) { 
                Send-SysAdminNotify -content "API：收到管理員指令，伺服器即將在 5 秒後重新啟動。" -title "系統操作" 
            }
            
            Start-Process "shutdown.exe" -ArgumentList "/r /t 5 /f /d p:4:1"
            [System.Environment]::Exit(0)
        }

    } catch { 
        Write-ApiLog "!!! API 迴圈錯誤: $($_.Exception.Message)" -Color Red
        $contextTask = $null 
    }
}