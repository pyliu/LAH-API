<#
.SYNOPSIS
    智慧控管與監控系統 (LAH) - PHP 8.5 與 Apache 雙軌轉發自動化安裝部署腳本
.DESCRIPTION
    適用於各地政事務所尚未部署 lah-api-v85 之 Windows 伺服器：
    1. 自動部署/解壓 PHP 8.5 NTS x64 至 C:\AppServ\php85
    2. 自動建立並優化 php.ini 設定（啟用 curl, gd, intl, mbstring, openssl, pdo_sqlite, sqlite3, odbc）
    3. 自動安全備份並注入 C:\AppServ\Apache24\conf\httpd.conf（Proxy FCGI 轉發、Alias 與可選之智慧無感 Rewrite 分流）
    4. 透過 C:\AppServ\node 之 PM2 註冊 lah-api-v85 守護進程 (Port 8085, 16 workers) 並保存開機自啟
    5. 重啟 Apache 2.4 並自動執行端點健康檢查
.PARAMETER PhpZipPath
    [離線模式] 本機 PHP 8.5 zip 壓縮檔完整路徑。若未指定則進入線上模式從官方鏡像下載。
.PARAMETER EnableSmartRewrite
    [進階轉址] 是否在 Apache DocumentRoot 加入 mod_rewrite 條件規則，讓既有舊路徑 /api/*.php 自動無感分流至 PHP 8.5。
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string]$PhpZipPath = "",

    [Parameter(Mandatory = $false)]
    [string]$InstallDir = "C:\AppServ\php85",

    [Parameter(Mandatory = $false)]
    [string]$ApacheDir = "C:\AppServ\Apache24",

    [Parameter(Mandatory = $false)]
    [string]$LahApiDir = "C:\AppServ\LAH-API",

    [Parameter(Mandatory = $false)]
    [int]$FastCgiPort = 8085,

    [Parameter(Mandatory = $false)]
    [switch]$EnableSmartRewrite = $false
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# --- 1. 檢查管理員身分 ---
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Error "【錯誤】本腳本需要系統管理員權限！請以「以系統管理員身分執行」啟動 PowerShell。"
    exit 1
}

Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "   LAH 智慧控管系統 - PHP 8.5 與 Apache 轉發部署工具" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Cyan

# --- 2. 檢查或下載 PHP 8.5 ---
Write-Host "`n[步驟 1/5] 準備 PHP 8.5 執行期環境..." -ForegroundColor Yellow

if (-not (Test-Path $InstallDir)) {
    New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
}

$phpCgiPath = Join-Path $InstallDir "php-cgi.exe"
if (Test-Path $phpCgiPath) {
    Write-Host "  [OK] $InstallDir 底下已存在 php-cgi.exe，跳過解壓縮步驟。" -ForegroundColor Green
} else {
    $zipTarget = ""
    if ($PhpZipPath -and (Test-Path $PhpZipPath)) {
        Write-Host "  [離線模式] 使用指定的本地壓縮包: $PhpZipPath" -ForegroundColor Green
        $zipTarget = $PhpZipPath
    } else {
        $tempZip = Join-Path $env:TEMP "php-8.5.12-nts-Win32-vs17-x64.zip"
        $downloadUrl = "https://windows.php.net/downloads/releases/archives/php-8.5.12-nts-Win32-vs17-x64.zip"
        Write-Host "  [線上模式] 正在從官方站台下載 PHP 8.5..." -ForegroundColor Cyan
        Write-Host "  下載位址: $downloadUrl" -ForegroundColor Gray
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -Uri $downloadUrl -OutFile $tempZip -UseBasicParsing
            $zipTarget = $tempZip
            Write-Host "  [OK] 下載完成。" -ForegroundColor Green
        } catch {
            Write-Error "【下載失敗】無法連線外網下載 PHP 8.5！若處於內網封閉環境，請先手動下載 zip 檔並透過參數執行：`n.\install_php85.ps1 -PhpZipPath 'D:\php-8.5.x-nts-Win32-vs17-x64.zip'"
            exit 1
        }
    }

    Write-Host "  正在解壓縮至 $InstallDir ..." -ForegroundColor Cyan
    Expand-Archive -Path $zipTarget -DestinationPath $InstallDir -Force
    Write-Host "  [OK] 解壓縮完成。" -ForegroundColor Green
}

# --- 3. 配置 php.ini ---
Write-Host "`n[步驟 2/5] 配置 $InstallDir\php.ini ..." -ForegroundColor Yellow
$phpIni = Join-Path $InstallDir "php.ini"
$phpIniProd = Join-Path $InstallDir "php.ini-production"
$phpIniDev = Join-Path $InstallDir "php.ini-development"

if (-not (Test-Path $phpIni)) {
    if (Test-Path $phpIniProd) {
        Copy-Item $phpIniProd $phpIni
    } elseif (Test-Path $phpIniDev) {
        Copy-Item $phpIniDev $phpIni
    } else {
        Write-Error "找不到 php.ini-production，無法建立 php.ini！"
        exit 1
    }
}

# 確保必要擴充套件開啟
$iniContent = Get-Content $phpIni -Raw -Encoding UTF8
$extensions = @("curl", "gd", "intl", "mbstring", "openssl", "pdo_sqlite", "sqlite3", "odbc")

# 設定 extension_dir = "ext"
if ($iniContent -match ';?extension_dir = "ext"') {
    $iniContent = $iniContent -replace ';?extension_dir = "ext"', 'extension_dir = "ext"'
}

foreach ($ext in $extensions) {
    # 取消分號註解
    $pattern = ";extension=$ext\b"
    if ($iniContent -match $pattern) {
        $iniContent = $iniContent -replace $pattern, "extension=$ext"
    } elseif ($iniContent -notmatch "(?m)^extension=$ext\b") {
        $iniContent += "`nextension=$ext`n"
    }
}

# 時區與基礎參數調整
$iniContent = $iniContent -replace ';?date\.timezone =.*', 'date.timezone = Asia/Taipei'
$iniContent = $iniContent -replace 'memory_limit = .*', 'memory_limit = 512M'
$iniContent = $iniContent -replace 'max_execution_time = .*', 'max_execution_time = 300'
$iniContent = $iniContent -replace 'post_max_size = .*', 'post_max_size = 100M'
$iniContent = $iniContent -replace 'upload_max_filesize = .*', 'upload_max_filesize = 100M'

Set-Content -Path $phpIni -Value $iniContent -Encoding UTF8
Write-Host "  [OK] php.ini 擴充功能與時區 (Asia/Taipei) 設定完成。" -ForegroundColor Green

# --- 4. 配置 Apache httpd.conf ---
Write-Host "`n[步驟 3/5] 配置 Apache 2.4 ($ApacheDir\conf\httpd.conf) ..." -ForegroundColor Yellow
$httpdConf = Join-Path $ApacheDir "conf\httpd.conf"
if (-not (Test-Path $httpdConf)) {
    Write-Error "找不到 Apache 設定檔：$httpdConf！請確認 -ApacheDir 參數。"
    exit 1
}

# 備份原設定檔
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$httpdBackup = Join-Path $ApacheDir "conf\httpd.conf.bak_$timestamp"
Copy-Item $httpdConf $httpdBackup
Write-Host "  [備份] 已建立備份檔案: $httpdBackup" -ForegroundColor Gray

$confContent = Get-Content $httpdConf -Raw -Encoding UTF8

# 確保必要模組載入
$modules = @("proxy_module", "proxy_fcgi_module", "headers_module", "rewrite_module")
foreach ($mod in $modules) {
    $modPattern = "(?m)^#(\s*LoadModule\s+$mod\s+modules/mod_$mod\.so)"
    if ($confContent -match $modPattern) {
        $confContent = $confContent -replace $modPattern, '$1'
        Write-Host "  [啟用模組] $mod" -ForegroundColor Cyan
    }
}

# 準備 LAH-API FastCGI 轉發設定區塊
$fcgiBlock = @"

# =====================================================================
# LAH-API (PHP 8.5 FastCGI via PM2 on 127.0.0.1:$FastCgiPort)
# =====================================================================
# 1. 優先定義相容 Alias：將舊呼叫端之 /api/ 映射至實體 /v1/ 目錄
Alias /LAH-API/api "$LahApiDir/v1"
Alias /lah-api/api "$LahApiDir/v1"

# 2. 服務根目錄映射
Alias /LAH-API "$LahApiDir"
Alias /lah-api "$LahApiDir"

<Directory "$LahApiDir">
    Header set Access-Control-Allow-Origin "*"
    Options Indexes FollowSymLinks
    AllowOverride None
    Require all granted

    <FilesMatch "\.php$">
        <If "-f %{REQUEST_FILENAME}">
            SetHandler "proxy:fcgi://127.0.0.1:$FastCgiPort/"
            ProxyFCGISetEnvIf "reqenv('SCRIPT_FILENAME') =~ m#^proxy:fcgi://127\.0\.0\.1:$FastCgiPort/(.*)$#" SCRIPT_FILENAME "$1"
        </If>
    </FilesMatch>
</Directory>

<Proxy "fcgi://127.0.0.1:$FastCgiPort/" enablereuse=on max=16>
    ProxySet timeout=300
</Proxy>
"@

if ($confContent -match "LAH-API \(PHP 8\.5 FastCGI") {
    Write-Host "  [提示] httpd.conf 已存在 LAH-API 區塊，正在更新連接埠與 Alias..." -ForegroundColor Yellow
    # 正則取代既有區塊
    $confContent = [System.Text.RegularExpressions.Regex]::Replace(
        $confContent,
        "(?s)# =====================================================================\s*# LAH-API.*?<\/Proxy>",
        $fcgiBlock.Trim()
    )
} else {
    Write-Host "  [追加] 追加 LAH-API 轉發與目錄存取區塊至 httpd.conf 最末端..." -ForegroundColor Cyan
    $confContent += "`n" + $fcgiBlock
}

# 若啟用智慧無感 Rewrite 分流
if ($EnableSmartRewrite) {
    $smartRewriteBlock = @"
    # --- LAH-API PHP 8.5 智慧無感分流規則 ---
    <IfModule mod_rewrite.c>
        RewriteEngine On
        # 若呼叫 /api/*.php 且新軌 v1/ 存在該檔案，自動以 FastCGI 轉發至 PHP 8.5
        RewriteCond "$LahApiDir/v1/`$1" -f
        RewriteRule ^api/(.*\.php)$ "fcgi://127.0.0.1:$FastCgiPort/$LahApiDir/v1/`$1" [P,L]
    </IfModule>
"@
    Write-Host "  [智慧分流] 啟用 mod_rewrite 條件轉址規則。" -ForegroundColor Cyan
    # 尋找 DocumentRoot 所在之 <Directory "C:/AppServ/www"> 注入
    if ($confContent -match '(<Directory\s+"C:[\/\\]AppServ[\/\\]www">[\s\S]*?)(<\/Directory>)') {
        if ($confContent -notmatch "LAH-API PHP 8\.5 智慧無感分流規則") {
            $confContent = $confContent -replace '(<Directory\s+"C:[\/\\]AppServ[\/\\]www">[\s\S]*?)(<\/Directory>)', "`$1`n$smartRewriteBlock`n`$2"
        }
    }
}

Set-Content -Path $httpdConf -Value $confContent -Encoding UTF8

# 測試 Apache 語法
$httpdExe = Join-Path $ApacheDir "bin\httpd.exe"
if (Test-Path $httpdExe) {
    $testResult = & $httpdExe -t 2>&1
    if ($LASTEXITCODE -ne 0 -or ($testResult -notmatch "Syntax OK")) {
        Write-Error "【Apache 語法錯誤】設定檔驗證失敗！`n$testResult`n已自動還原備份..."
        Copy-Item $httpdBackup $httpdConf -Force
        exit 1
    }
    Write-Host "  [OK] Apache 語法檢查通過 (Syntax OK)。" -ForegroundColor Green
}

# --- 5. PM2 守護程序啟動 ---
Write-Host "`n[步驟 4/5] 啟動 PM2 進程守護 (lah-api-v85)..." -ForegroundColor Yellow

$nodeDir = "C:\AppServ\node"
$pm2Cmd = "pm2"
if (Test-Path (Join-Path $nodeDir "pm2.cmd")) {
    $env:PATH = "$nodeDir;" + $env:PATH
    $pm2Cmd = Join-Path $nodeDir "pm2.cmd"
}

$ecosystemConfig = Join-Path $LahApiDir "ecosystem.config.js"
if (-not (Test-Path $ecosystemConfig)) {
    Write-Host "  [建立] $ecosystemConfig 不存在，自動產生標準設定檔..." -ForegroundColor Cyan
    $ecosystemContent = @"
module.exports = {
  apps: [
    {
      name: "lah-api-v85",
      script: "$($InstallDir.Replace('\', '\\'))\\php-cgi.exe",
      args: "-b 127.0.0.1:$FastCgiPort -c $($InstallDir.Replace('\', '\\'))\\php.ini",
      cwd: __dirname,
      interpreter: "none",
      exec_mode: "fork",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "1G",
      env: {
        PATH: "C:\\Tools\\instantclient_11_2;$($InstallDir.Replace('\', '\\'));" + process.env.PATH,
        NLS_LANG: "American_America.US7ASCII",
        PHP_FCGI_CHILDREN: "16",
        PHP_FCGI_MAX_REQUESTS: "10000"
      }
    }
  ]
};
"@
    Set-Content -Path $ecosystemConfig -Value $ecosystemContent -Encoding UTF8
}

try {
    # 刪除舊進程後以新設定重啟
    & $pm2Cmd delete lah-api-v85 2>$null | Out-Null
    & $pm2Cmd start $ecosystemConfig | Out-Null
    & $pm2Cmd save | Out-Null
    Write-Host "  [OK] PM2 已成功啟動 lah-api-v85 並保存快照至開機自啟。" -ForegroundColor Green
} catch {
    Write-Warning "【警告】PM2 啟動指令執行異常，請手動確認 Node.js 與 PM2 狀態。"
}

# --- 6. 重啟 Apache 服務與健康檢查 ---
Write-Host "`n[步驟 5/5] 重啟 Apache 2.4 並驗證服務..." -ForegroundColor Yellow
try {
    Restart-Service -Name "Apache2.4" -Force
    Write-Host "  [OK] Apache 2.4 服務重啟完成。" -ForegroundColor Green
} catch {
    Write-Warning "重啟 Apache2.4 服務失敗，請手動於 services.msc 檢查。"
}

Start-Sleep -Seconds 2

# 連接埠監聽檢查
$portCheck = Get-NetTCPConnection -LocalPort $FastCgiPort -State Listen -ErrorAction SilentlyContinue
if ($portCheck) {
    Write-Host "  [OK] FastCGI Port $FastCgiPort 正在監聽中 (PID: $($portCheck.OwningProcess))。" -ForegroundColor Green
} else {
    Write-Warning "【警告】未偵測到 Port $FastCgiPort 監聽，請檢查 PM2 日誌：pm2 logs lah-api-v85"
}

# 終端 API 回應驗證
Write-Host "`n================================================================" -ForegroundColor Cyan
Write-Host "   安裝完成！端點驗證結果：" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Cyan

$testEndpoints = @(
    @{ Name = "PHP 8.5 雙軌探針"; Url = "http://127.0.0.1/LAH-API/ver.php" },
    @{ Name = "新軌標準 API (/v1/)"; Url = "http://127.0.0.1/LAH-API/v1/system_json_api.php" },
    @{ Name = "新軌相容 API (/api/)"; Url = "http://127.0.0.1/LAH-API/api/system_json_api.php" },
    @{ Name = "線上舊軌 (mod_php7)"; Url = "http://127.0.0.1/ver.php" }
)

foreach ($ep in $testEndpoints) {
    try {
        $res = Invoke-WebRequest -Uri $ep.Url -UseBasicParsing -TimeoutSec 5
        Write-Host ("  [HTTP {0}] {1} ({2})" -f $res.StatusCode, $ep.Name, $ep.Url) -ForegroundColor Green
    } catch {
        Write-Host ("  [FAIL] {0} ({1}): {2}" -f $ep.Name, $ep.Url, $_.Exception.Message) -ForegroundColor Red
    }
}
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "部署腳本執行結束。詳細設定說明請參閱 DEPLOY_PHP85_APACHE_GUIDE.md。" -ForegroundColor Cyan
