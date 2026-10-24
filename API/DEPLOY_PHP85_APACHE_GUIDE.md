# 智慧控管與監控系統 (LAH) - PHP 8.5 雙軌運行與 Apache 轉址部署指南

> **適用對象**：系統架構師、各地政事務所系統管理師、自動化維運工程師。  
> **適用情境**：在既有僅運行 PHP 7.3 / 7.4 (`mod_php7`) 的 AppServ 伺服器中，新增並啟用 PHP 8.5 FastCGI (`lah-api-v85`)，並透過 Apache 2.4 完成 `/LAH-API` 雙軌路由轉發。  
> **核心標準**：
> * PHP 8.5 根目錄：`C:\AppServ\php85` (NTS x64)
> * API 專案路徑：`C:\AppServ\LAH-API` (端點位於 `v1/`)
> * FastCGI 監聽埠：`127.0.0.1:8085` (由 PM2 fork 守護，底層衍生 16 子程序)
> * Apache 伺服器：`C:\AppServ\Apache24`

---

## 一、系統拓撲與轉發架構

```mermaid
flowchart TD
    subgraph Clients["前端客戶端 (Clients)"]
        FE["LAH-FE 智慧管控前端 (Nuxt 2)"]
        MSG["lah-messenger 桌面即時通 (Electron)"]
    end

    subgraph ApacheGateway["Apache 2.4 閘道層 (C:\\AppServ\\Apache24 - Port 80)"]
        ROUTER{"URL 路由判斷"}
        MODPHP["mod_php7 模組<br/>(PHP 7.4 TS 執行 www/)"]
        MODFCGI["mod_proxy_fcgi 模組<br/>(反向代理轉發)"]
    end

    subgraph PM2Runtime["進程守護層 (PM2 Daemon)"]
        PM2_PHP85["PM2: lah-api-v85 (Port 8085)<br/>C:\\AppServ\\php85\\php-cgi.exe<br/>(16 Worker 子程序併發)"]
    end

    subgraph Codebase["程式碼目錄"]
        WWW["C:\\AppServ\\www\\<br/>(線上舊軌生產庫)"]
        LAH_API["C:\\AppServ\\LAH-API\\<br/>(新軌 PHP 8.5 程式碼庫)"]
    end

    FE & MSG -- "HTTP 請求" --> ROUTER
    ROUTER -- "舊路徑 /api/*" --> MODPHP
    ROUTER -- "新路徑 /LAH-API/v1/*<br/>相容路徑 /LAH-API/api/*" --> MODFCGI
    ROUTER -. "[進階] 若 /api/* 於新軌存在則無感分流 .-" --> MODFCGI

    MODPHP --> WWW
    MODFCGI --> PM2_PHP85
    PM2_PHP85 --> LAH_API
```

---

## 二、部署方式選擇：一鍵自動化 vs. 手動 SOP

本指南提供兩種部署途徑：
1. **[途徑 A] PowerShell 一鍵安裝腳本**：全自動完成下載/解壓、`php.ini` 設置、`httpd.conf` 備份注入、PM2 註冊與服務驗證（支援線上與離線模式）。
2. **[途徑 B] 手動逐步配置 SOP**：供管理師人工審核與逐項檢查設定檔。

---

## 三、[途徑 A] PowerShell 一鍵自動化部署

LAH 專案已隨附全自動安裝腳本 [`install_php85.ps1`](install_php85.ps1)。

### 1. 線上模式（伺服器具備外網連線）
以「系統管理員身分」開啟 PowerShell，執行：
```powershell
cd C:\AppServ\LAH-API
Set-ExecutionPolicy Bypass -Scope Process -Force
.\install_php85.ps1
```
> 若希望同時啟用「舊路徑 `/api/*` 無感智慧分流」，可附加 `-EnableSmartRewrite`：
> ```powershell
> .\install_php85.ps1 -EnableSmartRewrite
> ```

### 2. 離線模式（內網封閉環境）
1. 在可連外網電腦下載：
   * **PHP 8.5 NTS x64**：[官方下載連結 (VS17 x64 Non Thread Safe)](https://windows.php.net/downloads/releases/archives/php-8.5.12-nts-Win32-vs17-x64.zip)
   * **Visual C++ 2015-2022 可轉散發套件**：[vc_redist.x64.exe](https://aka.ms/vs/17/release/vc_redist.x64.exe)
2. 將檔案複製至伺服器（例如 `D:\OfflinePkg\`）。
3. 伺服器若未安裝 VC++ 2015-2022，先雙擊安裝 `vc_redist.x64.exe`。
4. 執行一鍵腳本並指定壓縮包路徑：
   ```powershell
   cd C:\AppServ\LAH-API
   Set-ExecutionPolicy Bypass -Scope Process -Force
   .\install_php85.ps1 -PhpZipPath "D:\OfflinePkg\php-8.5.12-nts-Win32-vs17-x64.zip" -EnableSmartRewrite
   ```

---

## 四、[途徑 B] 手動逐步配置 SOP

### 步驟 1：安裝 Visual C++ 執行期庫 (VC17 / VS 2015-2022)
PHP 8.5 由 Visual Studio 2022 (VS17) 編譯，必須先確保伺服器已安裝 64 位元執行期元件：
* 檔案：`vc_redist.x64.exe`
* 靜默安裝指令：`vc_redist.x64.exe /install /quiet /norestart`

---

### 步驟 2：解壓縮 PHP 8.5 至 `C:\AppServ\php85`
1. 將下載的 `php-8.5.x-nts-Win32-vs17-x64.zip` 解壓縮至 `C:\AppServ\php85`。
2. 目錄結構應如下：
   ```text
   C:\AppServ\php85\
   ├── php.exe
   ├── php-cgi.exe         # 關鍵：FastCGI 核心進程
   ├── php8.dll
   ├── php.ini-production
   ├── ext\                # 擴充模組庫
   │   ├── php_curl.dll
   │   ├── php_gd.dll
   │   ├── php_intl.dll
   │   ├── php_mbstring.dll
   │   ├── php_openssl.dll
   │   ├── php_pdo_sqlite.dll
   │   ├── php_sqlite3.dll
   │   └── ...
   ```

---

### 步驟 3：配置 `C:\AppServ\php85\php.ini`
1. 複製 `C:\AppServ\php85\php.ini-production` 並重新命名為 `C:\AppServ\php85\php.ini`。
2. 使用文字編輯器（如 Notepad++）開啟 `php.ini`，確認並修改以下參數：

```ini
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; 1. 啟用擴充套件目錄 (必須取消前方分號)
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
extension_dir = "ext"

;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; 2. 啟用核心相依擴充套件 (全數取消前方分號 ;)
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
extension=curl
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=odbc

;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; 3. 系統資源與執行期限制
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
memory_limit = 512M
max_execution_time = 300
post_max_size = 100M
upload_max_filesize = 100M

;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; 4. 時區設定 (避免 PHP 8 嚴格警告)
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
date.timezone = Asia/Taipei
```

3. 快速語法與擴充驗證：
   開啟命令提示字元或 PowerShell：
   ```powershell
   C:\AppServ\php85\php.exe -m
   ```
   確認清單內出現 `curl`、`gd`、`intl`、`mbstring`、`openssl`、`pdo_sqlite`、`sqlite3` 即代表正常。

---

### 步驟 4：修改 Apache 設定檔 (`C:\AppServ\Apache24\conf\httpd.conf`)

> [!IMPORTANT]
> 修改前請務必手動備份 `httpd.conf`（如複製為 `httpd.conf.bak`）。

#### 4.1 啟用必要模組 (取消前方 `#`)
搜尋以下 4 個模組，確保未被 `#` 註解：
```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
LoadModule headers_module modules/mod_headers.so
LoadModule rewrite_module modules/mod_rewrite.so
```

#### 4.2 注入 LAH-API FastCGI 轉發區塊
移至 `httpd.conf` 最末端，貼上以下標準配置：

```apache
# =====================================================================
# LAH-API (PHP 8.5 FastCGI via PM2 on 127.0.0.1:8085)
# =====================================================================
# 1. 優先定義相容 Alias：將舊呼叫端之 /api/ 映射至實體 /v1/ 目錄
Alias /LAH-API/api "C:/AppServ/LAH-API/v1"
Alias /lah-api/api "C:/AppServ/LAH-API/v1"

# 2. 服務根目錄映射
Alias /LAH-API "C:/AppServ/LAH-API"
Alias /lah-api "C:/AppServ/LAH-API"

<Directory "C:/AppServ/LAH-API">
    Header set Access-Control-Allow-Origin "*"
    Options -Indexes +FollowSymLinks
    AllowOverride None
    Require all granted

    # 安全防護：禁止直接存取機敏副檔名檔案 (設定檔、相依套件檔、測試腳本、文件等)
    <FilesMatch "^\.|\.(json|lock|xml|bat|ps1|md|ini|config|yml|yaml)$">
        Require all denied
    </FilesMatch>

    <FilesMatch "\.php$">
        # 關鍵防護：檢查檔案存在才轉發，並剝除 Proxy 前綴以支援 Windows 路徑
        <If "-f %{REQUEST_FILENAME}">
            SetHandler "proxy:fcgi://127.0.0.1:8085/"
            ProxyFCGISetEnvIf "reqenv('SCRIPT_FILENAME') =~ m#^proxy:fcgi://127\.0\.0\.1:8085/(.*)$#" SCRIPT_FILENAME "$1"
        </If>
    </FilesMatch>
</Directory>

# 安全防護：禁止外網直接存取內部核心目錄 (原始碼 include、單元測試 tests、套件庫 vendor)
<Directory "C:/AppServ/LAH-API/include">
    Require all denied
</Directory>

<Directory "C:/AppServ/LAH-API/tests">
    Require all denied
</Directory>

<Directory "C:/AppServ/LAH-API/vendor">
    Require all denied
</Directory>

# 建立 FastCGI 連線池，最大 16 併發，逾時 300 秒
<Proxy "fcgi://127.0.0.1:8085/" enablereuse=on max=16>
    ProxySet timeout=300
</Proxy>
```

#### 4.3 [進階可選] 既有舊路徑 `/api/*` 無感智慧分流 (Smart Rewrite)
若希望**前端客戶端完全不改 URL**，原本呼叫 `/api/*.php` 的請求，只要新軌 `v1/` 已經移植完成就**自動走 PHP 8.5**，未移植的繼續走舊版 PHP 7：

在 `httpd.conf` 原本的 `<Directory "C:/AppServ/www">` 區塊內部加上：

```apache
    # -------------------------------------------------------------
    # LAH-API 舊路徑漸進智慧分流：
    # 當請求 /api/<file>.php 時，若 C:/AppServ/LAH-API/v1/<file>.php 存在
    # 則無感由 PHP 8.5 FastCGI 執行，否則由現有 mod_php7 執行
    # -------------------------------------------------------------
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond "C:/AppServ/LAH-API/v1/$1" -f
        RewriteRule ^api/(.*\.php)$ "fcgi://127.0.0.1:8085/C:/AppServ/LAH-API/v1/$1" [P,L]
    </IfModule>
```

#### 4.4 檢驗 Apache 語法
```powershell
C:\AppServ\Apache24\bin\httpd.exe -t
```
> 必須輸出：`Syntax OK`。若有報錯請勿重啟服務！

---

### 步驟 5：配置 PM2 守護進程 (`C:\AppServ\LAH-API\ecosystem.config.js`)

在 `C:\AppServ\LAH-API\ecosystem.config.js` 建立守護設定：

```javascript
module.exports = {
  apps: [
    {
      name: "lah-api-v85",
      script: "C:\\AppServ\\php85\\php-cgi.exe",
      args: "-b 127.0.0.1:8085 -c C:\\AppServ\\php85\\php.ini",
      cwd: __dirname,
      interpreter: "none",          // 告知 PM2 為原生 EXE
      exec_mode: "fork",            // 原生 EXE 必須使用 fork 模式
      instances: 1,                 // 由內部 PHP_FCGI_CHILDREN 接管多進程
      autorestart: true,
      watch: false,
      max_memory_restart: "1G",
      env: {
        PATH: "C:\\Tools\\instantclient_11_2;C:\\AppServ\\php85;" + process.env.PATH,
        NLS_LANG: "American_America.US7ASCII",
        // 核心併發參數：底層衍生 16 個子進程共享 Port 8085
        PHP_FCGI_CHILDREN: "16",
        // 處理滿 10,000 次請求自動平滑重啟，徹底防止 FastCGI 記憶體洩漏
        PHP_FCGI_MAX_REQUESTS: "10000"
      }
    }
  ]
};
```

#### 啟動並儲存開機自啟：
```powershell
# 引入 Node / PM2 環境路徑
$env:PATH = "C:\AppServ\node;" + $env:PATH

cd C:\AppServ\LAH-API
pm2 start ecosystem.config.js
pm2 save
```

確認狀態：
```powershell
pm2 status
```
> `lah-api-v85` 狀態應為 `online`。

---

### 步驟 6：重啟 Apache 服務與全功能驗收

1. 重啟 Apache 服務：
   ```powershell
   Restart-Service -Name "Apache2.4" -Force
   ```
2. 檢查 Port `8085` 是否監聽中：
   ```powershell
   Get-NetTCPConnection -LocalPort 8085 -State Listen
   ```
3. 執行端點驗證清單：

| 測試網址 | 預期結果 | 代表意義 |
|---|---|---|
| `http://127.0.0.1/LAH-API/ver.php` | 回傳 JSON：`"php_version": "8.5.x"`, `"sapi": "cgi-fcgi"` | PHP 8.5 雙軌探針正常運作 |
| `http://127.0.0.1/LAH-API/v1/system_json_api.php` | 回傳 JSON（HTTP 200） | 新版標準 API 轉發成功 |
| `http://127.0.0.1/LAH-API/api/system_json_api.php` | 回傳 JSON（HTTP 200） | 舊版相容 API 轉發成功 |
| `http://127.0.0.1/ver.php` | 顯示 PHP 7.4.x `phpinfo` 頁面 | 舊版 Apache `mod_php7` 運作正常，雙軌互不干擾 |

---

## 五、常見問題與故障排除 (FAQ)

### Q1：Apache 重啟時報錯 `Cannot load modules/mod_proxy_fcgi.so into server`
* **原因**：Apache 未載入 `mod_proxy.so`。
* **解法**：`mod_proxy_fcgi.so` 相依於 `mod_proxy.so`，請確認 `httpd.conf` 中 `LoadModule proxy_module` 位於 `LoadModule proxy_fcgi_module` 之前，且兩者皆已啟用。

### Q2：瀏覽器開啟 `/LAH-API/` 端點回傳 `503 Service Unavailable`
* **原因**：Apache 無法連線至 `127.0.0.1:8085`。
* **排查步驟**：
  1. 執行 `Get-NetTCPConnection -LocalPort 8085 -State Listen` 檢查 Port 8085 是否處於 Listen 狀態。
  2. 執行 `pm2 status` 檢查 `lah-api-v85` 是否處於 `errored` 或不斷重啟。
  3. 檢視 PM2 日誌：`pm2 logs lah-api-v85 --lines 50`。
  4. 確認 `C:\AppServ\php85\php.exe -v` 能否於命令提示字元正常執行（若缺少 VC++ 執行期庫會跳出系統彈窗報錯）。

### Q3：為什麼 PM2 內 `instances` 只能設為 1，不能設為 cluster 模式？
* **原因**：PM2 cluster 模式僅原生支援 Node.js。原生 `.exe`（如 `php-cgi.exe`）若設定多個實例，第二個進程會因為搶占同一個 TCP 監聽埠（Port 8085）而觸發 `WSAEADDRINUSE (Error 10048)` 崩潰。
* **正確解法**：在 PM2 設定 `instances: 1` 守護單一父進程，並在 `env` 注入 `PHP_FCGI_CHILDREN: "16"`，由 PHP FastCGI 原生底層衍生 16 個 worker 子進程高併發處理請求。
