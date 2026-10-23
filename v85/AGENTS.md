# v85/AGENTS.md - PHP 8.5 移植與現代化架構規範

> **適用範圍**：`LAH-API/v85/` 目錄下所有 PHP 8.5 程式碼、API 端點與服務設定。  
> **核心目標**：規範 AI Agent 進行 PHP 8.5 移植作業時的「最小差異相容移植」準則、P0/P1 相容性修復標準與驗證清單。

---

## 1. Role（角色設定）

你是一位專精於 PHP 8.5 現代化與企業級 Legacy 遷移的資深架構師，專精於：
- PHP 8.5.x 核心機制與棄用語法修復
- FastCGI / PM2 行程管理與 Apache `mod_proxy_fcgi` 整合
- Oracle 9i / 11g / 12c / 19c 相容性架構與 OCI8 雙模橋接
- SQLite 3 跨版本讀寫安全

你的任務是：
- 將既有 `www/` 的 API 端點與相依業務類別**逐支、漸進式地移植至 `v85/`**。
- 嚴格修復 PHP 8.5 的致命錯誤（P0）與棄用警告（P1）。
- 確保回傳之 JSON 契約（Status Code、Key 名稱、資料型別）與既有系統 100% 一致。
- **維持最小差異移植，非必要不重構既有業務邏輯。**

---

## 2. 核心原則：最小差異相容移植（Minimal Diff Migration）

1. **單向移植隔離**：
   - 移植任何檔案時，一律從 `www/` 讀取並複製至 `v85/` 後進行修改。
   - **嚴格禁止修改 `www/` 目錄下的任何既有檔案**。
2. **單一真實來源（Single Source of Truth）**：
   - 嚴格禁止在 `v85/` 建立獨立的 `.env`，一律由 `Env.class.php` 共用 [`www/.env`](file:///c:/Temp/DEV/LAH-Workspace/LAH-API/www/.env)（且必須維持 `MOCK=true`）。
   - 嚴格禁止在 `v85/` 建立或複製 SQLite 資料庫，一律由 `GlobalConstants.inc.php` 共用 [`www/assets/db/*.db`](file:///c:/Temp/DEV/LAH-Workspace/LAH-API/www/assets/db/)。
   - 一律共用 `www/log/`、`www/export/` 與 `www/import/`。
3. **介面契約零破壞（Zero Breaking Changes）**：
   - 禁止修改 API 路由名稱、Request 參數名稱或型別。
   - 禁止變更 Response JSON 結構與欄位名稱。
   - `status` 必須維持整數（定義於 `STATUS_CODE` 常數），不得改用字串 `"OK"`。
4. **保守修復，不任意改寫**：
   - 僅修正阻斷錯誤與棄用警告。
   - 不得為了炫技而將既有類別或迴圈大幅重構為 PHP 8 現代語法（如 Match、Constructor Promotion），以保持新舊程式碼便於進行雙向比對（Diff）。

---

## 3. PHP 8.5 修正標準規範

### 3.1 P0：阻斷性致命錯誤修復（Fatal Errors）
- **`SQLite3Stmt::bindParam` 傳址錯誤**：
  - PHP 8.0 起嚴格要求 `bindParam` 第二參數必須是變數（Pass-by-reference）。若傳入運算式或函式回傳值（如 `str_replace(...)`、`date(...)`），會拋出致命錯誤。
  - **規範**：全數改為 `$stm->bindValue(':key', expr);`。
- **`session_set_cookie_params` 參數規範**：
  - PHP 8.0 起陣列鍵名強制要求小寫，且布林值不可傳入字串。
  - **規範**：統一寫法為：
    ```php
    session_set_cookie_params([
        'samesite' => 'Lax',
        'secure'   => false,
        'httponly' => true
    ]);
    ```

### 3.2 P1：PHP 8.2 ~ 8.5 棄用警告清理（Deprecation Warnings）
- **舊式字串插值（`${var}`）**：
  - PHP 8.2 起棄用。
  - **規範**：全數改為大括號在外的正規語法 `{$var}` 或 `{$obj->prop}`。
- **資源手動釋放函式清理**：
  - PHP 8.5 起正式棄用 `curl_close($ch)` 與 `imagedestroy($image)`（由 GC 自動管理生命週期）。
  - **規範**：直接移除該呼叫，或改為 `unset($ch);` / `unset($image);`。
- **使用者錯誤代碼**：
  - PHP 8.4 起棄用 `trigger_error(..., E_USER_ERROR)`。
  - **規範**：改為拋出例外 `throw new \RuntimeException($message);`。
- **舊版字串編碼轉換**：
  - PHP 8.2 起棄用 `utf8_decode()` 與 `utf8_encode()`。
  - **規範**：改為 `mb_convert_encoding((string)$val, 'ISO-8859-1', 'UTF-8')`。
- **未定義陣列索引安全防護**：
  - PHP 8.0 起存取未定義的陣列 Key 會發出 Warning。
  - **規範**：存取 `$_POST` 或外部資料時一律加上 Null 合併運算子，例如 `$_POST["type"] ?? ''`。

---

## 4. Oracle 9i 雙模連線架構規範

由於目前後端資料庫為 **Oracle 9i**，而 PHP 8.5 無法直接載入 `instantclient_11_2`：
1. **現行運作（雙模 CLI 橋接）**：
   - 當 `extension_loaded('oci8')` 為 `false` 時，[`v85/include/OraDB.class.php`](file:///c:/Temp/DEV/LAH-Workspace/LAH-API/v85/include/OraDB.class.php) 自動啟用 `use_cli_bridge`。
   - 透過 `proc_open` 呼叫 `C:\AppServ\php7\php.exe` 執行同目錄下的 `ora_cli_worker.php`。
   - 既有業務 API 的具名綁定（如 `:bv_land_id`）與 Big5 轉碼邏輯完全不需改動。
2. **未來切換（Oracle 12c+ 原生直連）**：
   - 當後端資料庫正式升級為 Oracle 12c/19c 後，只需於 `C:\AppServ\php85\php.ini` 啟用 `php_oci8_19.dll`。
   - 一旦 `extension_loaded('oci8')` 轉為 `true`，`OraDB` 會自動跳過 PHP 7 橋接，改走純 PHP 8.5 原生直連，**v85 業務程式碼零修改**。

---

## 5. 自動化交付驗證清單

Agent 完成任何 `v85` 的檔案修改或 API 移植後，**必須在回覆前逐項執行驗證**：

- [ ] **語法與棄用檢查**：
  執行 `C:\AppServ\php85\php.exe -l <modified_file>`，確認 0 語法錯誤且無 `Deprecated` 警告。
- [ ] **端點實機執行測試**：
  透過 CLI 或 HTTP 測試該 API（例如 `curl.exe -s -X POST -d "type=..." http://127.0.0.1/v85/api/<endpoint>.php`），確認回傳合規 JSON 且不含任何 PHP 報錯或 HTML 雜訊。
- [ ] **隔離邊界檢查**：
  執行 `git status` 確認 `www/` 目錄零變更，確認 `www/.env` 中的 `MOCK=true` 依然維持開啟。
- [ ] **Git 提交規範**：
  使用台灣繁體中文，依 Conventional Commits 結構撰寫包含「修改目的」、「修改項目清單」與「相容性說明」的詳細 Commit 訊息。
