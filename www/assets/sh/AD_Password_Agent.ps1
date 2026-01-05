<#
    AD Password Reset Agent (HttpListener)

    *** 重要編碼提示 (Important Encoding Notice) ***
    若您的 Windows 環境預設編碼為 BIG5 (常見於繁體中文舊版系統)，
    請務必確認此腳本檔案 (.ps1) 是以 BIG5 (ANSI) 編碼儲存。
    若以 UTF-8 (無 BOM) 儲存，在某些舊版 PowerShell 環境下可能會因為中文字元解析錯誤導致執行失敗。
    建議使用 Notepad++ 或記事本開啟後，選擇「另存新檔」並確認編碼為 ANSI (BIG5)。

    *** 防火牆設定提示 (Firewall Configuration) ***
    此腳本預設監聽 TCP Port 8888。請務必在 Windows 防火牆中新增輸入規則 (Inbound Rule)，
    允許 TCP Port 8888 通過，否則 PHP 主機無法連線。
    快速設定指令 (請以管理員身分在 PowerShell 執行):
    New-NetFirewallRule -DisplayName "AD Password Agent" -Direction Inbound -LocalPort 8888 -Protocol TCP -Action Allow

    功能：接收 HTTP POST 請求，執行 AD 密碼重設
    需求：
        1. 執行此腳本的帳號需有 "重設密碼" 的權限 (如 Domain Admins 或被委派的帳號)
        2. 需安裝 RSAT-AD-PowerShell 模組 (通常 AD 主機都有)
        3. 請以【系統管理員身分】執行 PowerShell
#>

# --- 設定區域 ---
$ListeningPort = 8888
$SharedSecret  = "YOUR_SECRET_KEY_123456"  # 請務必修改此密鑰，PHP 端需一致
$LogFilePath   = "C:\Temp\AD_Password_Agent.log"
$MaxLogSize    = 1MB
# ----------------

# --- 日誌函式 (含檔案輪替邏輯) ---
function Write-Log {
    param (
        [Parameter(Mandatory=$true)]
        [string]$Message,
        
        [string]$Color = "White",
        
        [switch]$IsError
    )

    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogLine = "[$Timestamp] $Message"

    # 1. 輸出到螢幕 (Console)
    if ($IsError) {
        Write-Host $LogLine -ForegroundColor Red
    } else {
        Write-Host $LogLine -ForegroundColor $Color
    }

    # 2. 輸出到檔案 (File)
    try {
        # 確保目錄存在
        $LogDir = [System.IO.Path]::GetDirectoryName($LogFilePath)
        if (-not (Test-Path $LogDir)) {
            New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
        }

        # 檢查檔案大小並輪替 (Log Rotation)
        if (Test-Path $LogFilePath) {
            $FileItem = Get-Item $LogFilePath
            if ($FileItem.Length -gt $MaxLogSize) {
                $BackupPath = "$LogFilePath.old"
                # 簡單輪替：刪除舊備份 -> 目前檔案改名為備份 -> 建立新檔
                if (Test-Path $BackupPath) { Remove-Item $BackupPath -Force }
                Move-Item $LogFilePath $BackupPath -Force
                
                # 記錄輪替事件
                $RotateMsg = "[$Timestamp] [System] Log file rotated (exceeded 1MB)."
                Add-Content -Path $LogFilePath -Value $RotateMsg -Encoding UTF8
            }
        }

        # 寫入內容
        Add-Content -Path $LogFilePath -Value $LogLine -Encoding UTF8
    } catch {
        # 若寫入檔案失敗，僅顯示在螢幕，不中斷程式
        Write-Host "[$Timestamp] [System Error] Failed to write to log file: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# 1. 檢查並載入 AD 模組
try {
    Import-Module ActiveDirectory -ErrorAction Stop
    Write-Log "ActiveDirectory 模組載入成功。" -Color Green
} catch {
    Write-Log "找不到 ActiveDirectory 模組。請確認此電腦已安裝 RSAT 工具 (AD PowerShell)。" -IsError
    exit
}

# 2. 啟動 HTTP Listener
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:${ListeningPort}/")

Write-Log "AD Password Agent is listening on port $ListeningPort..." -Color Cyan
Write-Log "Shared Secret: $SharedSecret" -Color Yellow
Write-Log "Log File Output: $LogFilePath (Max size: 1MB)" -Color Gray

try {
    $listener.Start()
} catch {
    Write-Log "無法啟動監聽器。`n可能原因：`n1. 沒有以【系統管理員身分】執行。`n2. Port $ListeningPort 已經被佔用 (請嘗試 netstat -ano | findstr $ListeningPort)。" -IsError
    exit
}

# 3. 進入監聽迴圈
while ($listener.IsListening) {
    try {
        $context = $listener.GetContext() # 這裡會阻塞直到收到請求
        $request = $context.Request
        $response = $context.Response

        # 預設回應
        $responseString = '{"status":"error", "message":"Invalid Request"}'
        $response.ContentType = "application/json"
        $statusCode = 400

        if ($request.HttpMethod -eq "POST" -and $request.Url.LocalPath -eq "/reset-password") {
            try {
                # 讀取 Body
                if ($request.HasEntityBody) {
                    $reader = New-Object System.IO.StreamReader($request.InputStream)
                    $body = $reader.ReadToEnd()
                    $reader.Close()
                    
                    try {
                        $data = $body | ConvertFrom-Json
                    } catch {
                        throw "Invalid JSON Format"
                    }
                } else {
                    throw "Empty Body"
                }

                # 驗證密鑰
                if ($data.secret -ne $SharedSecret) {
                    throw "Invalid API Key"
                }

                # 取得參數
                $targetUser = $data.account
                $newPassword = $data.password

                if ([string]::IsNullOrWhiteSpace($targetUser) -or [string]::IsNullOrWhiteSpace($newPassword)) {
                    throw "Missing account or password"
                }

                Write-Log "重設請求: 使用者 $targetUser" -Color Green

                # 執行密碼重設
                $securePwd = ConvertTo-SecureString $newPassword -AsPlainText -Force
                
                # 尋找使用者 DN (確認使用者存在)
                $userObj = Get-ADUser -Identity $targetUser -ErrorAction Stop
                
                # 重設密碼
                Set-ADAccountPassword -Identity $userObj.DistinguishedName -NewPassword $securePwd -Reset -ErrorAction Stop
                
                # (選用) 解鎖帳戶 (如果帳戶被鎖定，改密碼通常希望順便解鎖)
                # Unlock-ADAccount -Identity $userObj.DistinguishedName
                
                $responseString = '{"status":"success", "message":"Password reset successfully"}'
                $statusCode = 200
                Write-Log "  -> 成功 ($targetUser)" -Color Green

            } catch {
                $errMsg = $_.Exception.Message
                if ($null -eq $errMsg) { $errMsg = $_.ToString() }
                
                $errParams = @{
                    status = "error"
                    message = $errMsg
                }
                # 確保回傳的是純文字 JSON
                $responseString = $errParams | ConvertTo-Json -Compress
                $statusCode = 500
                Write-Log "  -> 失敗: $errMsg" -IsError
            }
        }

        # 回傳結果
        $buffer = [System.Text.Encoding]::UTF8.GetBytes($responseString)
        $response.ContentLength64 = $buffer.Length
        $response.StatusCode = $statusCode
        $response.OutputStream.Write($buffer, 0, $buffer.Length)
        $response.Close()

    } catch {
        # 捕捉監聽迴圈本身的錯誤 (防止視窗崩潰)
        Write-Log "監聽過程發生未預期錯誤: $($_.Exception.Message)" -IsError
        Start-Sleep -Seconds 1
    }
}