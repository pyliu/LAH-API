<#
    AD Password Reset Agent (HttpListener)
    功能：接收 HTTP POST 請求，執行 AD 密碼重設
    需求：
        1. 執行此腳本的帳號需有 "重設密碼" 的權限 (如 Domain Admins 或被委派的帳號)
        2. 需安裝 RSAT-AD-PowerShell 模組 (通常 AD 主機都有)
        3. 請以【系統管理員身分】執行 PowerShell
    PS. This file's Encoding must be set to BIG5
#>

# --- 設定區域 ---
$ListeningPort = 8888
$SharedSecret  = "YOUR_SECRET_KEY_123456"  # 請務必修改此密鑰，PHP 端需一致
# ----------------

# 1. 檢查並載入 AD 模組
try {
    Import-Module ActiveDirectory -ErrorAction Stop
    Write-Host "ActiveDirectory 模組載入成功。" -ForegroundColor Green
} catch {
    Write-Error "找不到 ActiveDirectory 模組。請確認此電腦已安裝 RSAT 工具 (AD PowerShell)。"
    exit
}

# 2. 啟動 HTTP Listener
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://*:${ListeningPort}/")

Write-Host "AD Password Agent is listening on port $ListeningPort..." -ForegroundColor Cyan
Write-Host "Shared Secret: $SharedSecret" -ForegroundColor Yellow

try {
    $listener.Start()
} catch {
    Write-Error "無法啟動監聽器。`n可能原因：`n1. 沒有以【系統管理員身分】執行。`n2. Port $ListeningPort 已經被佔用 (請嘗試 netstat -ano | findstr $ListeningPort)。"
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

                Write-Host "[$(Get-Date)] 重設請求: 使用者 $targetUser" -ForegroundColor Green

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
                Write-Host "  -> 成功" -ForegroundColor Green

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
                Write-Host "  -> 失敗: $errMsg" -ForegroundColor Red
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
        Write-Host "監聽過程發生未預期錯誤: $($_.Exception.Message)" -ForegroundColor Red
        Start-Sleep -Seconds 1
    }
}