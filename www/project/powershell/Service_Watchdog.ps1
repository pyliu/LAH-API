<#
.SYNOPSIS
    Windows 系統服務自動守護與異常喚醒腳本 (Service Watchdog)

.DESCRIPTION
    這是一支獨立的背景監控腳本，專門用於偵測並修復 Windows 系統中意外停止的服務。
    支援兩種監控模式：
    1. 指定清單模式：精準監控特定陣列中的服務狀態。
    2. 全局掃描模式：自動抓出所有「啟動類型為自動」但狀態為「已停止」的服務並將其喚醒。

.PARAMETER ServiceNames
    [字串陣列] 指定要監控的服務名稱或顯示名稱。若留空 (@())，則自動進入「全局掃描模式」。
    預設值包含常見的 MSSQL 與 VMware 相關服務。

.PARAMETER IntervalSeconds
    [整數] 每次掃描的間隔秒數。預設為 60 秒。僅在非 SinglePass 模式下生效。

.PARAMETER LogPath
    [字串] 監控日誌檔的存放目錄。預設為 C:\Temp\ServiceWatchdogLogs。

.PARAMETER SinglePass
    [開關] 若加上此參數，腳本只會執行一次掃描與喚醒，隨後立即結束。
    非常適合交由「Windows 工作排程器」或「資產管理系統 (如神網)」每 5 分鐘觸發一次。

.EXAMPLE
    # 以預設值在背景持續監控 (每 60 秒掃描一次)
    .\Service_Watchdog.ps1

.EXAMPLE
    # 單次執行模式 (掃描完即結束，適合放入 Task Scheduler)
    .\Service_Watchdog.ps1 -SinglePass

.EXAMPLE
    # 自訂要監控的服務，並縮短間隔為 30 秒
    .\Service_Watchdog.ps1 -ServiceNames "Spooler", "W3SVC" -IntervalSeconds 30

.NOTES
    作者: 資深系統整合工程師
    版本: 1.2 (修正 Param 區塊優先級問題)
    相容性: Windows PowerShell 5.1 / PowerShell Core 7+
    權限要求: 必須以「系統管理員 (Administrator)」身分執行，否則無法啟動服務。
    檔案編碼: 請務必以 UTF-8 with BOM 或 ANSI 格式儲存，避免 PS5.1 發生亂碼。
#>

[CmdletBinding()]
param(
    [Parameter()]
    [string[]]$ServiceNames = @(
        "MSSQLSERVER", 
        "VMware Tools", 
        "VMware Snapshot Provider", 
        "VMware Alias Manager and Ticket Service",
        "VMware SVGA 協助程式服務",
        "Volume Shadow Copy"
    ),

    [Parameter()]
    [ValidateRange(10, 3600)]
    [int]$IntervalSeconds = 60,

    [Parameter()]
    [string]$LogPath = "C:\Temp\ServiceWatchdogLogs",

    [Parameter()]
    [switch]$SinglePass
)

# 確保 Console 輸出中文字元不會變成亂碼 (必須放在 param 區塊之後)
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ErrorActionPreference = 'Continue'
$MaxLogSizeBytes = 5MB
$MaxLogHistory = 5

# -------------------------------------------------------------------------
# 函數：日誌記錄模組
# -------------------------------------------------------------------------
function Write-WatchdogLog {
    param(
        [Parameter(Mandatory=$true)][string]$Message,
        [Parameter()][ConsoleColor]$Color = "Gray",
        [Parameter()][switch]$IsWarningOrError
    )
    
    try {
        # 確保目錄存在
        if (-not (Test-Path $LogPath)) { New-Item -ItemType Directory -Path $LogPath -Force | Out-Null }
        
        $today = Get-Date -Format "yyyy-MM-dd"
        $fullPath = Join-Path $LogPath "ServiceWatchdog_$today.log"
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
        
        # 簡易的檔案大小輪替 (Log Rotation) 機制
        if (Test-Path $fullPath) {
            $fileItem = Get-Item $fullPath -ErrorAction SilentlyContinue
            if ($null -ne $fileItem -and $fileItem.Length -ge $MaxLogSizeBytes) {
                if (Test-Path "$fullPath.$MaxLogHistory") { Remove-Item "$fullPath.$MaxLogHistory" -Force }
                for ($i = $MaxLogHistory - 1; $i -ge 1; $i--) { 
                    $src = "$fullPath.$i"; $dest = "$fullPath.$($i + 1)"
                    if (Test-Path $src) { Move-Item $src $dest -Force } 
                }
                Move-Item $fullPath "$fullPath.1" -Force
            }
        }
        
        Add-Content -Path $fullPath -Value $logEntry -Encoding UTF8
        Write-Host $logEntry -ForegroundColor $Color

        # 若有原生系統日誌紀錄需求，可在此處寫入 EventLog
        if ($IsWarningOrError) {
            Write-EventLog -LogName "Application" -Source "Application Error" -EventId 1000 -EntryType Warning -Message "Service Watchdog: $Message" -ErrorAction SilentlyContinue
        }
    } catch {
        Write-Warning "日誌寫入失敗: $($_.Exception.Message)"
    }
}

# -------------------------------------------------------------------------
# 函數：喚醒特定服務
# -------------------------------------------------------------------------
function Wake-TargetService {
    param(
        [Parameter(Mandatory=$true)]
        [System.ServiceProcess.ServiceController]$Svc
    )
    
    Write-WatchdogLog "[警告] 異常偵測：服務「$($Svc.DisplayName)」($($Svc.Name)) 已停止，嘗試喚醒..." -Color Yellow -IsWarningOrError
    
    try {
        Start-Service -Name $Svc.Name -ErrorAction Stop
        
        # 等待服務啟動，最多等待 30 秒
        $waited = 0
        do {
            Start-Sleep -Seconds 2
            $waited += 2
            $Svc.Refresh()
        } while ($Svc.Status -ne 'Running' -and $waited -lt 30)

        if ($Svc.Status -eq 'Running') {
            Write-WatchdogLog "[成功] 喚醒成功：服務「$($Svc.DisplayName)」已成功恢復執行。" -Color Green
        } else {
            Write-WatchdogLog "[錯誤] 喚醒超時：服務「$($Svc.DisplayName)」啟動後 30 秒仍未達到 Running 狀態 (目前狀態: $($Svc.Status))。" -Color Red -IsWarningOrError
        }
    } catch {
        Write-WatchdogLog "[失敗] 啟動服務「$($Svc.DisplayName)」時發生系統錯誤: $($_.Exception.Message)" -Color Red -IsWarningOrError
    }
}

# -------------------------------------------------------------------------
# 函數：核心守護邏輯 (掃描與比對)
# -------------------------------------------------------------------------
function Watch-AutoServices {
    try {
        Write-WatchdogLog "[開始] 執行服務狀態健康度檢查..." -Color DarkGray
        
        $targetServices = @()
        $isModeA = ($ServiceNames -and $ServiceNames.Count -gt 0)

        if ($isModeA) {
            Write-WatchdogLog "[模式] 指定清單模式 (共 $($ServiceNames.Count) 項)" -Color DarkGray
            
            # 模式 A：只檢查清單內指定的服務
            foreach ($svcName in $ServiceNames) {
                $svcName = $svcName.Trim()
                if ([string]::IsNullOrWhiteSpace($svcName)) { continue }
                
                # 優先用 Name 找，找不到再用 DisplayName 找
                $svc = Get-Service -Name $svcName -ErrorAction SilentlyContinue
                if ($null -eq $svc) {
                    $svc = Get-Service -ErrorAction SilentlyContinue | Where-Object { $_.DisplayName -eq $svcName } | Select-Object -First 1
                }
                
                if ($null -ne $svc) { 
                    $targetServices += $svc 
                } else {
                    Write-WatchdogLog "  -- [提醒] 找不到系統服務 [$svcName]，請檢查名稱是否正確。" -Color DarkYellow
                }
            }
            
            # 驗證清單內的服務狀態
            foreach ($svc in $targetServices) {
                $startMode = 'Unknown'
                try {
                    $wmiSvc = Get-WmiObject Win32_Service -Filter "Name='$($svc.Name)'" -ErrorAction SilentlyContinue
                    if ($wmiSvc) { $startMode = $wmiSvc.StartMode }
                } catch {}

                Write-WatchdogLog "  -- 檢查: $($svc.DisplayName) | 狀態: $($svc.Status) | 啟動類型: $startMode" -Color DarkGray

                # 只要在清單內且狀態非 Running，就觸發喚醒
                if ($svc.Status -ne 'Running') {
                    Wake-TargetService -Svc $svc
                }
            }
            
        } else {
            Write-WatchdogLog "[模式] 全局掃描模式 (所有啟動類型為自動的服務)" -Color DarkGray
            
            # 模式 B：自動掃描所有啟動類型=自動且狀態非 Running 的服務
            $targetServices = Get-WmiObject Win32_Service -ErrorAction SilentlyContinue |
                Where-Object { $_.StartMode -eq 'Auto' -and $_.State -ne 'Running' } |
                ForEach-Object { Get-Service -Name $_.Name -ErrorAction SilentlyContinue } |
                Where-Object { $_ -ne $null }
                
            # 防洪機制：若都正常，只印一行提示
            if ($targetServices.Count -eq 0) {
                Write-WatchdogLog "  -- [健康] 所有自動啟動服務皆正常運行中。" -Color Green
            } else {
                Write-WatchdogLog "  -- [警告] 發現 $($targetServices.Count) 個自動啟動服務處於異常停止狀態！" -Color Yellow -IsWarningOrError
                foreach ($svc in $targetServices) {
                    Wake-TargetService -Svc $svc
                }
            }
        }
        
        Write-WatchdogLog "[結束] 本次週期完成。" -Color DarkGray

    } catch {
        Write-WatchdogLog "[例外] 掃描時發生未預期錯誤: $($_.Exception.Message)" -Color Red -IsWarningOrError
    }
}

# -------------------------------------------------------------------------
# 主程式入口
# -------------------------------------------------------------------------

# 權限檢查：是否為系統管理員
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Warning "!!! 權限不足：請以「系統管理員 (Administrator)」身分執行此腳本，否則無法喚醒服務。"
    exit
}

Write-WatchdogLog "=================================================" -Color Cyan
Write-WatchdogLog " 系統服務自動守護腳本 (Service Watchdog) 已啟動" -Color Cyan
Write-WatchdogLog "=================================================" -Color Cyan

if ($SinglePass) {
    # 單次執行模式 (適合排程器)
    Write-WatchdogLog "[資訊] 執行模式：單次掃描 (SinglePass)" -Color Cyan
    Watch-AutoServices
    Write-WatchdogLog "[資訊] 單次掃描完畢，腳本結束。" -Color Cyan
} else {
    # 無窮迴圈模式 (適合常駐背景)
    Write-WatchdogLog "[資訊] 執行模式：背景常駐 (間隔: $IntervalSeconds 秒)" -Color Cyan
    while ($true) {
        Watch-AutoServices
        Start-Sleep -Seconds $IntervalSeconds
    }
}