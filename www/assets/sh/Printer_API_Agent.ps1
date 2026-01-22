<#
.SYNOPSIS
<<<<<<< HEAD
    ¸ê²`¨t²Î¾ã¦X¤uµ{®v¹ê§@ª©¥» - Print Server HTTP API & Proactive Monitor
    ª©¥»¡Gv14.5 (Power Status Detection via ICMP)
    ­×¥¿¡G
    1. ·s¼Wºô¸ô¦s¬¡°»´ú¡G§Q¥Î ICMP Ping °Ï¤À¡u¯u¥¿Â_¹q¡v»P¡u³nÅéÂ÷½u¡v¡C
    2. Àu¤Æª¬ºA´y­z¡G¨Ì¾Ú Ping µ²ªG¦^¶Ç "¥i¯à¥¼¶}¾÷" ©Î "ºô¸ô³qºZ" µ¥ºë½T¸ê°T¡C
    3. ºû«ù v14.4 ªº©Ò¦³¥\¯à¡GPDF ¤W¶Ç¡BÂù­±¦C¦L¡B¦ÛÂ¡³qª¾¡B¤é»x²M²z»P PS 2.0 ¬Û®e¡C
    4. §¹¥ş¬Û®e PowerShell 2.0 (Windows Server 2008 SP2) ¦Ü 2019¡C
.NOTES
    ?? ´ú¸Õ«ü¥O
    curl -H "X-API-KEY: %API_KEY%" http://%SERVER_IP%:8888/printers
=======
    è³‡æ·±ç³»çµ±æ•´åˆå·¥ç¨‹å¸«å¯¦ä½œç‰ˆæœ¬ - Print Server HTTP API & Proactive Monitor
    ç‰ˆæœ¬ï¼šv13.6 (CORS Support Update)
    ä¿®æ­£ï¼š
    1. æ–°å¢ CORS æ”¯æ´ï¼šå›æ‡‰æ¨™é ­åŠ å…¥ Access-Control-Allow-Origin: *ã€‚
    2. è™•ç† OPTIONS é æª¢è«‹æ±‚ï¼šåœ¨é©—è­‰ API Key å‰å„ªå…ˆå›æ‡‰ OPTIONSï¼Œç¢ºä¿ç€è¦½å™¨è·¨åŸŸè«‹æ±‚æˆåŠŸã€‚
    3. ç¶­æŒ v13.5 çš„é›™é¢åˆ—å°å®¹éŒ¯ã€PDF ä¸Šå‚³ã€è‡ªç™’é€šçŸ¥èˆ‡æ‰€æœ‰ç›£æ§åŠŸèƒ½ã€‚
    4. å®Œå…¨ç›¸å®¹ PowerShell 2.0 (Windows Server 2008 SP2) è‡³ 2019ã€‚
.NOTES
    ?? é›™é¢åˆ—å°æ³¨æ„äº‹é …ï¼š
    æ­¤åŠŸèƒ½ä¾è³´ 'Set-PrintConfiguration' æŒ‡ä»¤ï¼Œé€šå¸¸åƒ…å…§å»ºæ–¼ Windows Server 2012 åŠä»¥ä¸Šç‰ˆæœ¬ã€‚
    è‹¥åœ¨ Windows Server 2008 R2/SP2 ä¸ŠåŸ·è¡Œï¼Œé›™é¢åˆ—å°åƒæ•¸å°‡è¢«å¿½ç•¥ï¼ˆæ—¥èªŒæœƒé¡¯ç¤ºè­¦å‘Šï¼‰ï¼Œä½†åˆ—å°å‹•ä½œä»æœƒåŸ·è¡Œï¼ˆä¾é è¨­å€¼ï¼‰ã€‚

    ?? PDF ä¸Šå‚³åˆ—å°æ¸¬è©¦æŒ‡ä»¤ (CMD)
    curl -v -X POST -H "X-API-KEY: %API_KEY%" --data-binary "@test.pdf" "http://%SERVER_IP%:8888/printer/print-pdf?name=PrinterName&duplex=long"
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
#>

# -------------------------------------------------------------------------
# 1. åŸºç¤è¨­å®šå€
# -------------------------------------------------------------------------
$port               = 8888
$apiKey             = "YourSecretApiKey123"      
$logPath            = "C:\Temp"
$uploadPath         = "C:\Temp\Uploads"           
$maxLogSizeBytes    = 10MB                       
$maxHistory         = 5                          
$logRetentionDays   = 7                   

<<<<<<< HEAD
# --- PDF ¾\Åª¾¹¸ô®|²M³æ ---
$pdfReaderPaths     = @(
    "C:\Program Files (x86)\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe",
    "C:\Program Files\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe",
    "C:\FoxitReader\Foxit Reader.exe",
    "C:\Program Files\Adobe\Acrobat DC\Acrobat\Acrobat.exe",
    "C:\Program Files (x86)\Adobe\Acrobat Reader DC\Reader\AcroRd32.exe"
)
=======
# --- PDF é–±è®€å™¨è·¯å¾‘ ---
$pdfReaderPath      = "C:\Program Files (x86)\Foxit Software\Foxit PDF Reader\FoxitPDFReader.exe"
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74

$notifyIp           = "220.1.34.75"
$notifyEndpoint     = "/api/notification_json_api.php"
$notifyUrl          = "http://$notifyIp$notifyEndpoint"
$notifyChannels     = @("HA10013859")

# --- ç›£æ§æ™‚æ®µèˆ‡é »ç‡ ---
$checkIntervalSec   = 60                  
$errorThreshold     = 5                   
$monitorStartHour   = 8
$monitorEndHour     = 17
$monitorDays        = @("Monday", "Tuesday", "Wednesday", "Thursday", "Friday")

# --- æ™ºæ…§è‡ªç™’è¨­å®š ---
$enableAutoCleanup  = $true               
$zombieTimeMinutes  = 10                  
$enableAutoHeal     = $true               
$maxStuckPrinters   = 3                   

# --- ä½‡åˆ—ç›£æ§è¨­å®š ---
$queueThreshold     = 20                  
$queueStuckLimit    = 5                   

# --- å°è¡¨æ©Ÿæ’é™¤è¨­å®š ---
$excludeKeywords    = @("PDF", "XPS", "Fax", "OneNote", "Microsoft Shared Fax")
$manualExcludePrinters = @(
    "ç¯„ä¾‹å°è¡¨æ©Ÿåç¨±_A",
    "ç¯„ä¾‹å°è¡¨æ©Ÿåç¨±_B"
)

# å…¨å±€ç‹€æ…‹è®Šæ•¸
$global:PrinterStateCache   = New-Object System.Collections.Hashtable
$global:PrinterErrorCount   = New-Object System.Collections.Hashtable 
$global:ExcludedPrinters    = New-Object System.Collections.Hashtable 
$global:QueueStuckCount     = New-Object System.Collections.Hashtable 
$global:LastQueueCount      = New-Object System.Collections.Hashtable 
$global:IsFirstRun          = $true
$global:ValidPdfReader      = $null

# -------------------------------------------------------------------------
# 2. æ ¸å¿ƒå‡½æ•¸åº«
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
                    Write-ApiLog ">>> [æ—¥èªŒæ¸…ç†] åˆªé™¤éæœŸæ—¥èªŒ: $($file.Name)"
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
    param([Parameter(Mandatory=$true)][string]$content, [string]$title = "å°è¡¨æ©Ÿç³»çµ±é€šçŸ¥")
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
        Write-ApiLog ">>> [æº–å‚™ç™¼é€é€šçŸ¥] æ¨™é¡Œ: $title"
        [void]$wc.UploadString($notifyUrl, "POST", $postBody)
        Write-ApiLog ">>> [é€šçŸ¥ç™¼é€æˆåŠŸ]"
    } catch { Write-ApiLog "!!! [é€šçŸ¥ç™¼é€å¤±æ•—] $($_.Exception.Message)" }
}

function Invoke-SpoolerSelfHealing {
    param([string]$reason)
    Write-ApiLog "!!! [è‡ªç™’å•Ÿå‹•] $reason"
    $startMsg = "ç³»çµ±åµæ¸¬åˆ°åš´é‡ç•°å¸¸ ($reason)ï¼Œæ­£åœ¨è‡ªå‹•åŸ·è¡Œæ·±åº¦ä¿®å¾©æµç¨‹ã€‚"
    Send-SysAdminNotify -title "?? ç³»çµ±è‡ªå‹•è‡ªç™’å•Ÿå‹•" -content $startMsg
    try {
        Stop-Service "Spooler" -Force; Start-Sleep -Seconds 3
        $spoolPath = "C:\Windows\System32\spool\PRINTERS"
        if (Test-Path $spoolPath) { Get-ChildItem -Path "$spoolPath\*" -Include *.* -Force | Remove-Item -Force }
        Start-Service "Spooler"
        Send-SysAdminNotify -title "? ç³»çµ±è‡ªå‹•è‡ªç™’å®Œæˆ" -content "æœå‹™å·²é‡å•Ÿä¸¦æ¸…ç†æš«å­˜æª”ã€‚"
    } catch { Send-SysAdminNotify -title "? ç³»çµ±è‡ªå‹•è‡ªç™’å¤±æ•—" -content "éŒ¯èª¤: $($_.Exception.Message)" }
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
    
    # ªì©l¤Æ Ping ª«¥ó (½Æ¥Î¥H¸`¬Ù¸ê·½)
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
            if (($errState -band 16) -eq 16) { $errorList.Add("ç¼ºç´™"); $isHardwareError = $true }
            if (($errState -band 128) -eq 128) { $errorList.Add("æ©Ÿè“‹é–‹å•Ÿ"); $isHardwareError = $true }
            if (($errState -band 256) -eq 256) { $errorList.Add("å¤¾ç´™"); $isHardwareError = $true }
            if (($errState -band 512) -eq 512) { $isOffline = $true }
            if (($errState -band 1024) -eq 1024) { $errorList.Add("ç¡¬é«”æ•…éšœ"); $isHardwareError = $true }
        }
        
        $finalStatus = "Ready"
        if ($isHardwareError) { 
            $finalStatus = "Error (" + [string]::Join(", ", $errorList) + ")" 
        } elseif ($isOffline) { 
            $finalStatus = "Offline" 
        } else {
            switch ($p.PrinterStatus) {
<<<<<<< HEAD
                1 { $finalStatus = "Error (¥¼ª¾ - ¥i¯à­ì¦]: ÅX°Ê­­¨î/SNMP¨üªı/¯S®íµwÅéª¬ºA)" }
                2 { $finalStatus = "Error (¨ä¥L - ½ĞÀË¬d³]³Æ­±ªO)" }
                4 { $finalStatus = "Printing" }
                5 { $finalStatus = "Warmup" }
=======
                1 { $finalStatus = "Error (æœªçŸ¥)" } 2 { $finalStatus = "Error (å…¶ä»–)" }
                4 { $finalStatus = "Printing" } 5 { $finalStatus = "Warmup" }
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
                default { $finalStatus = "Ready" }
            }
        }
        
        # --- IP ¸ÑªR»P¦s¬¡°»´ú ---
        $pPort = $p.PortName
        $pIP = ""
        if ($portMap.ContainsKey($pPort)) { $pIP = $portMap[$pPort] } else { $pIP = $pPort }
        
        # [·s¼W] °w¹ï IPv4 ¶i¦æ§Ö³t Ping ÀË´ú (Timeout 200ms)
        if ($pIP -match "^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$") {
            try {
                $reply = $pingSender.Send($pIP, 200)
                if ($reply.Status -ne "Success") {
                    # Ping ¥¢±Ñ
                    if ($finalStatus -eq "Offline") {
                        $finalStatus = "Offline (µL¦^À³ - ¥i¯à¥¼¶}¾÷)"
                    } elseif ($finalStatus -like "Ready*") {
                        $finalStatus = "Warning (µL¦^À³ - ¥i¯àÂ_½u©Î¥¼¶}¾÷)"
                    }
                } else {
                    # Ping ¦¨¥\
                    if ($finalStatus -eq "Offline") {
                        $finalStatus = "Offline (³nÅéÂ÷½u - ºô¸ô³qºZ)"
                    }
                }
            } catch { 
                # Ping µo¥Í¿ù»~ (¦p DNS ¸ÑªR¥¢±Ñ)
                if ($finalStatus -eq "Offline") { $finalStatus = "Offline (ºô¸ô¿ù»~)" }
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
    Write-ApiLog ">>> [ç›£æ§] å·¡æª¢é–‹å§‹..."
    $printers = Get-PrinterStatusData
    $batchAlerts = New-Object System.Collections.Generic.List[string]
    $stuckPrinters = 0
    if ($enableAutoCleanup) {
        $zombies = Get-WmiObject -Class Win32_PrintJob | Where-Object { ($_.JobStatus -like "*Error*" -or $_.JobStatus -like "*Deleting*") }
        if ($null -ne $zombies) {
            foreach ($z in $zombies) { $batchAlerts.Add("?? [è‡ªç™’] æ¸…ç†å¡ä½ä½œæ¥­: $($z.JobId)"); $z.Delete() }
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
            if ($global:PrinterErrorCount[$name] -eq $errorThreshold) { $batchAlerts.Add("â— [ç•°å¸¸] å°è¡¨æ©Ÿ [$name] $pStatus") }
        } else {
            if ($global:PrinterErrorCount[$name] -ge $errorThreshold) { $batchAlerts.Add("â—‹ [æ¢å¾©] å°è¡¨æ©Ÿ [$name] å·²æ¢å¾©æ­£å¸¸ã€‚") }
            $global:PrinterErrorCount[$name] = 0
        }
        if ($pJobs -ge $queueThreshold -and $pJobs -ge $global:LastQueueCount[$name]) {
            $global:QueueStuckCount[$name]++
            if ($global:QueueStuckCount[$name] -eq $queueStuckLimit) {
                $batchAlerts.Add("?? [å µå¡] å°è¡¨æ©Ÿ [$name] ä½‡åˆ—åœæ»¯ ($pJobs æ¡ˆ)ã€‚")
                $stuckPrinters++
            }
        } else { $global:QueueStuckCount[$name] = 0 }
        $global:LastQueueCount[$name] = $pJobs
    }
    if ($enableAutoHeal -and $stuckPrinters -ge $maxStuckPrinters) { Invoke-SpoolerSelfHealing -reason "å¤šå°å°è¡¨æ©ŸåŒæ™‚å µå¡"; return }
    if ($global:IsFirstRun) { $global:IsFirstRun = $false; return }
    # åœç”¨ç¶­è­·æ‘˜è¦é€šçŸ¥: if ($batchAlerts.Count -gt 0) { Send-SysAdminNotify -content ([string]::Join("`n", $batchAlerts)) -title "å°è¡¨æ©Ÿç¶­è­·æ‘˜è¦" }
}

# -------------------------------------------------------------------------
# 3. ä¸»ç¨‹åº (HttpListener)
# -------------------------------------------------------------------------
Write-ApiLog "--- ¨t²Îªì©l¤Æ: ¥¿¦b·j´M PDF ¾\Åª¾¹ ---"
foreach ($path in $pdfReaderPaths) {
    if (Test-Path $path) {
        $global:ValidPdfReader = $path
        Write-ApiLog "[¨t²Îªì©l¤Æ] ¤wÂê©w PDF ¾\Åª¾¹: $path"
        break
    }
}
if ($null -eq $global:ValidPdfReader) {
    Write-ApiLog "[¨t²Îªì©l¤Æ] Äµ§i: ¥¼°»´ú¨ì«ü©w²M³æ¤¤ªº¾\Åª¾¹¡A«áÄò¦C¦L±N­°¯Å¨Ï¥Î¨t²Î¹w³]ÃöÁp¡C"
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:$port/")
<<<<<<< HEAD
try { $listener.Start(); Write-ApiLog "--- ¦øªA¾¹ v14.5 ¤W½u (Ping ¦s¬¡°»´ú¥\¯à¤w±Ò¥Î) ---" } catch { exit }
=======
try { $listener.Start(); Write-ApiLog "--- ä¼ºæœå™¨ v13.6 ä¸Šç·š (CORS æ”¯æ´å·²å•Ÿç”¨) ---" } catch { exit }
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74

$nextCheck = Get-Date; $nextHeart = Get-Date; $contextTask = $null

while ($listener.IsListening) {
    try {
        $now = Get-Date
        if ($now -ge $nextHeart) { Write-ApiLog "[å­˜æ´»] ç›£è½ä¸­..."; $nextHeart = $now.AddSeconds(60) }
        if ($now -ge $nextCheck) {
            $day = $now.DayOfWeek.ToString()
            if (($now.Hour -ge $monitorStartHour) -and ($now.Hour -lt $monitorEndHour) -and ($monitorDays -contains $day)) {
                Test-PrinterHealth
            } else { Write-ApiLog ">>> [éå·¥ä½œæ™‚æ®µ] è·³éå·¡æª¢ã€‚" }
            $nextCheck = $now.AddSeconds($checkIntervalSec)
        }

        if ($null -eq $contextTask) { $contextTask = $listener.BeginGetContext($null, $null) }
        if (-not $contextTask.AsyncWaitHandle.WaitOne(1000)) { continue }

        $context = $listener.EndGetContext($contextTask); $contextTask = $null
        $request = $context.Request; $response = $context.Response; $path = $request.Url.AbsolutePath.ToLower()
        Write-ApiLog ">>> [è«‹æ±‚] ä¾†è‡ª: $($request.RemoteEndPoint) è·¯å¾‘: $path"

<<<<<<< HEAD
        # --- [CORS] ---
        $response.AddHeader("Access-Control-Allow-Origin", "*")
        $response.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $response.AddHeader("Access-Control-Allow-Headers", "*") 

        if ($request.HttpMethod -eq "OPTIONS") {
            $response.StatusCode = 200; $response.Close()
            Write-ApiLog ">>> [CORS] ¹wÀË½Ğ¨D³q¹L"; continue
=======
        # --- [CORS] è·¨åŸŸæ¨™é ­è¨­å®š ---
        $response.AddHeader("Access-Control-Allow-Origin", "*")
        $response.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        $response.AddHeader("Access-Control-Allow-Headers", "*")

        # --- [CORS] OPTIONS é æª¢è«‹æ±‚è™•ç† ---
        if ($request.HttpMethod -eq "OPTIONS") {
            $response.StatusCode = 200
            $response.Close()
            Write-ApiLog ">>> [CORS] é æª¢è«‹æ±‚é€šé"
            continue
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
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
                    $res.data = $logContent; $res.success = $true; $res.message = "¤wÅª¨ú³Ì«á $count ¦æ"
                } else { $res.message = "¤µ¤é©|µL¤é»xÀÉ®×" }
            }
            elseif ($path -eq "/printer/print-pdf") {
                if ($request.HttpMethod -eq "POST") {
                    $pName = $request.QueryString["name"]
                    $pObj = Get-WmiObject Win32_Printer | Where-Object { $_.Name -eq $pName }
                    
                    if ($null -ne $pObj) {
<<<<<<< HEAD
=======
                        # --- é›™é¢åˆ—å° (å®¹éŒ¯ç‰ˆ) ---
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
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
<<<<<<< HEAD
                                        Write-ApiLog ">>> [³]©w] ¤Á´«Âù­±¼Ò¦¡: $targetMode"
                                        Set-PrintConfiguration -PrinterName $pName -DuplexingMode $targetMode -ErrorAction Stop
                                        $restoreDuplex = $true
                                    }
                                } catch { Write-ApiLog ">>> [³]©wÄµ§i] µLªkÅÜ§óÂù­±³]©w: $($_.Exception.Message)" }
                            } else { Write-ApiLog ">>> [©¿²¤] ¤£¤ä´© Set-PrintConfiguration" }
=======
                                        Write-ApiLog ">>> [è¨­å®š] å˜—è©¦åˆ‡æ›é›™é¢æ¨¡å¼: $targetMode"
                                        Set-PrintConfiguration -PrinterName $pName -DuplexingMode $targetMode -ErrorAction Stop
                                        $restoreDuplex = $true
                                    }
                                } catch { 
                                    Write-ApiLog ">>> [è¨­å®šè­¦å‘Š] ç„¡æ³•è®Šæ›´é›™é¢è¨­å®š (é©…å‹•å¯èƒ½ä¸æ”¯æ´): $($_.Exception.Message)ã€‚å°‡ä¾é è¨­å€¼åˆ—å°ã€‚" 
                                }
                            } else { Write-ApiLog ">>> [å¿½ç•¥] ç³»çµ±ä¸æ”¯æ´ Set-PrintConfiguration" }
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
                        }

                        $fileName = "Upload_$(Get-Date -Format 'yyyyMMdd_HHmmss').pdf"
                        $savePath = Join-Path $uploadPath $fileName
                        Write-ApiLog ">>> [ä¸Šå‚³] æ¥æ”¶ PDF: $fileName"
                        
                        $fs = New-Object System.IO.FileStream($savePath, [System.IO.FileMode]::Create)
                        $buffer = New-Object byte[] 8192
                        do {
                            $read = $request.InputStream.Read($buffer, 0, $buffer.Length)
                            if ($read -gt 0) { $fs.Write($buffer, 0, $read) }
                        } while ($read -gt 0); $fs.Close()
                        
                        Write-ApiLog ">>> [åˆ—å°] èª¿ç”¨ PDF é–±è®€å™¨..."
                        try {
<<<<<<< HEAD
                            if ($null -ne $global:ValidPdfReader) {
                                Write-ApiLog ">>> [¦C¦L] ¨Ï¥Î§Ö¨ú¸ô®|: $($global:ValidPdfReader)"
=======
                            if ((Test-Path $pdfReaderPath) -eq $true) {
                                Write-ApiLog ">>> [åˆ—å°] ä½¿ç”¨æŒ‡å®šç¨‹å¼: $pdfReaderPath"
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
                                $argList = "/t ""$savePath"" ""$pName"""
                                $proc = Start-Process -FilePath $global:ValidPdfReader -ArgumentList $argList -PassThru -WindowStyle Hidden
                                $proc.WaitForExit(10000)
                            } else {
<<<<<<< HEAD
                                Write-ApiLog ">>> [¦C¦L] ¹Á¸Õ Shell PrintTo (¥¼°»´ú¨ì«ü©w¾\Åª¾¹)..."
                                $proc = Start-Process -FilePath $savePath -Verb PrintTo -ArgumentList """$pName""" -PassThru -WindowStyle Hidden
                                $proc.WaitForExit(10000)
                            }
                            $res.success = $true; $res.message = "PDF ¤w¶Ç°e¦Ü¦C¦L¦î¦C"
                            Send-SysAdminNotify -content "API¡GPDF ¤W¶Ç¨Ãµo°e¦Ü [$pName] (Âù­±:$($null -ne $duplexReq))¡C" -title "»·ºİ¦C¦L"
                        } catch {
                            $res.message = "¦C¦L¥¢±Ñ: $($_.Exception.Message)"
                            Write-ApiLog "!!! [¦C¦L¿ù»~] $($_.Exception.Message)"
=======
                                Write-ApiLog ">>> [åˆ—å°] å˜—è©¦ Shell PrintTo..."
                                $proc = Start-Process -FilePath $savePath -Verb PrintTo -ArgumentList """$pName""" -PassThru -WindowStyle Hidden
                                $proc.WaitForExit(10000)
                            }
                            
                            $res.success = $true
                            $res.message = "PDF å·²å‚³é€è‡³åˆ—å°ä½‡åˆ—"
                            Send-SysAdminNotify -content "APIï¼šPDF ä¸Šå‚³ä¸¦ç™¼é€è‡³ [$pName] (é›™é¢:$($null -ne $duplexReq))ã€‚" -title "é ç«¯åˆ—å°"
                        } catch {
                            $res.message = "åˆ—å°å•Ÿå‹•å¤±æ•—: $($_.Exception.Message)"
                            Write-ApiLog "!!! [åˆ—å°éŒ¯èª¤] $($_.Exception.Message)"
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
                        }

                        if ($restoreDuplex) {
                            try {
<<<<<<< HEAD
                                Write-ApiLog ">>> [ÁÙ­ì] «ì´_Âù­±³]©w"
                                Set-PrintConfiguration -PrinterName $pName -DuplexingMode $oldDuplexMode -ErrorAction SilentlyContinue
                            } catch {}
                        }
                    } else { $res.message = "§ä¤£¨ì«ü©wªº¦Lªí¾÷: $pName" }
                } else { $res.message = "¶È¤ä´© POST ¤èªk" }
=======
                                Write-ApiLog ">>> [é‚„åŸ] æ¢å¾©é›™é¢è¨­å®š: $oldDuplexMode"
                                Set-PrintConfiguration -PrinterName $pName -DuplexingMode $oldDuplexMode -ErrorAction SilentlyContinue
                            } catch {}
                        }

                    } else { $res.message = "æ‰¾ä¸åˆ°æŒ‡å®šçš„å°è¡¨æ©Ÿ: $pName" }
                } else { $res.message = "åƒ…æ”¯æ´ POST æ–¹æ³•" }
>>>>>>> 8bdf81439113f6b595bf50628a6547f6a61cdf74
            }
            elseif ($path -eq "/printer/status") {
                $pName = $request.QueryString["name"]
                $all = Get-PrinterStatusData
                $target = $null
                foreach($item in $all) { if($item.Name -eq $pName) { $target = $item; break } }
                if ($null -ne $target) { $res.data = $target; $res.success = $true }
                else { $res.message = "æ‰¾ä¸åˆ°æŒ‡å®šçš„å°è¡¨æ©Ÿ" }
            }
            elseif ($path -eq "/printer/refresh") {
                $pName = $request.QueryString["name"]
                $pObj = Get-WmiObject -Class Win32_Printer | Where-Object { $_.Name -eq $pName }
                if ($null -ne $pObj) {
                    $pObj.Pause(); Start-Sleep -Milliseconds 500; $pObj.Resume()
                    $res.success = $true
                    Send-SysAdminNotify -content "APIï¼šå°è¡¨æ©Ÿ [$pName] æ‰‹å‹•é‡æ–°æ•´ç†æˆåŠŸã€‚" -title "ç¶­è­·æ“ä½œ"
                } else { $res.message = "æ‰¾ä¸åˆ°æŒ‡å®šçš„å°è¡¨æ©Ÿ" }
            }
            elseif ($path -eq "/printer/clear") {
                $pName = $request.QueryString["name"]
                $jobs = Get-WmiObject Win32_PrintJob | Where-Object { $_.Name -like "*$pName*" }
                if ($jobs) { foreach($j in $jobs){$j.Delete()} }
                $res.success = $true; Send-SysAdminNotify -content "[$pName] æ‰‹å‹•æ¸…ç†å®Œæˆã€‚" -title "æ‰‹å‹•æ“ä½œ"
            }
            elseif ($path -eq "/service/restart-spooler") {
                try {
                    Restart-Service "Spooler" -Force
                    $res.success = $true
                    Send-SysAdminNotify -content "APIï¼šSpooler æœå‹™å·²é‡å•Ÿã€‚" -title "æœå‹™æ“ä½œ"
                } catch { Write-ApiLog "!!! [é‡å•Ÿå¤±æ•—] $($_.Exception.Message)" }
            }
            elseif ($path -eq "/service/self-heal") {
                Invoke-SpoolerSelfHealing -reason "ç®¡ç†å“¡é ç«¯ç™¼å‹•æ·±åº¦ä¿®å¾©"; $res.success = $true
            }
            else { 
                $response.StatusCode = 404 
                Write-ApiLog "!!! [è·¯å¾‘éŒ¯èª¤] ç„¡æ³•è¾¨è­˜çš„è·¯å¾‘: $path"
            }
        }

        $buffer = [System.Text.Encoding]::UTF8.GetBytes((ConvertTo-SimpleJson $res))
        $response.ContentType = "application/json"; $response.OutputStream.Write($buffer, 0, $buffer.Length); $response.Close()
    } catch { 
        Write-ApiLog "!!! [ç³»çµ±éŒ¯èª¤] $($_.Exception.Message)"
        $contextTask = $null 
    }
}