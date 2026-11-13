# LAH-API 專案架構與雙軌治理契約

> **適用範圍**：`LAH-API` 儲存庫根目錄及其轄下之 `www/`（PHP 7.3/7.4 線上生產軌道）與 `API/`（PHP 8.5 現代化移植軌道）。

---

## 1. 雙軌架構拓撲與目錄分流

由 Apache 2.4（Port 80）作為統一閘道分流：

| 目錄 | 軌道角色 | 執行環境與技術棧 | 路由與監聽埠 | 專屬規範指標 |
|---|---|---|---|---|
| `./www/` | **線上生產軌道 (Legacy Production)** | PHP 7.3 / 7.4、Apache `mod_php7`、Oracle 9i (OCI8)、SQLite 3 | `http://127.0.0.1/api/*.php` (Port 80) | 修改前讀取 [`www/AGENTS.md`](./www/AGENTS.md) |
| `./API/` | **現代化移植軌道 (PHP 8.5 Migration)** | PHP 8.5.12 NTS、PM2 FastCGI (`lah-api-v85`, `php-cgi.exe`)、OCI8 雙模橋接 | `http://127.0.0.1/LAH-API/v1/*.php` (轉發 Port 8085，相容 `/LAH-API/api/*`) | 修改前讀取 [`API/AGENTS.md`](./API/AGENTS.md) |

---

## 2. 跨目錄邊界與單一真實來源鐵律

### 2.1 雙軌業務同步 vs. 純移植隔離
1. **常規業務功能新增、API 修改或 Bug 修復（雙軌同步鐵律）**：
   - 凡修改 `www/api/*.php` 或 `www/include/*.class.php`（新增端點動作、調整 Request/Response、修改業務邏輯或修補缺陷），**必須同步更新 `API/` 對應檔案**（如 `www/api/user_json_api.php` ↔ `API/v1/user_json_api.php`、`www/include/ADService.class.php` ↔ `API/include/ADService.class.php`）。
   - 同步至 `API/` 時，必須確保相容 PHP 8.5（如 `bindParam` 傳址改 `bindValue`、消除棄用語法），並分別以對應版本執行 `php -l` 驗證。
2. **純 `API/` 專案移植任務**：
   - 僅從 `www/` 單向讀取原始碼複製至 `API/` 進行 PHP 8.5 適配，**嚴禁修改 `www/` 既有檔案**。

### 2.2 共用資源單一真實來源（Single Source of Truth）
- **唯一環境設定檔（[`www/.env`](./www/.env)）**：
  - `API/include/Env.class.php` 與 `GlobalConstants.inc.php` 直接共用 `www/.env`；**嚴禁在 `API/` 建立獨立 `.env`**。
  - **強制 MOCK 模式**：除非取得人工明確授權，否則 `www/.env` 中的 `MOCK=true` 嚴禁改為 `false` 或註解。
- **共用 SQLite 資料庫與目錄**：
  - 兩軌一律共用 [`www/assets/db/*.db`](./www/assets/db/)、`www/log/`、`www/export/` 與 `www/import/`；**嚴禁在 `API/` 建立資料庫複本**，防止狀態漂移。

---

## 3. 本機執行環境、測試入口與 Schema 參考

- **PHP 執行檔**：
  - PHP 7.4（對應 `www/`）：`d:\DEV\PHP7\php.exe`
  - PHP 8.5（對應 `API/`）：`d:\DEV\PHP85\php.exe`
- **測試入口**：
  - 全量測試套件：於 `API/` 目錄執行 `phpunit`（定義於 `API/composer.json` 與 `API/phpunit.xml`；`API/tests/index.php` 為防存取守衛，非測試入口）。
  - 免 PHPUnit 獨立單檔測試：直接執行 `API/tests/Unit/Export/TaxExportRCD0101Test.php`、`TaxExportRCD0102Test.php` 與 `TaxExportBatchIntegrationTest.php`（建議同時以 PHP 7.4 與 PHP 8.5 執行）。
- **資料庫欄位參考**：
  - 撰寫地方稅務局產製 SQL 或 Mock 測試夾具前，先查閱 [`docs/oracle-tax-schema.md`](./docs/oracle-tax-schema.md) 核對實際 Oracle 9i 欄位。
