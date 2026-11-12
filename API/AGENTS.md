# API/AGENTS.md — PHP 8.5 現代化移植軌道規範

> **適用範圍**：`LAH-API/API/` 目錄下所有 PHP 8.5 程式碼、API 端點與 FastCGI 服務設定。

---

## 1. 最小差異相容移植原則（Minimal Diff Migration）

1. **共用單一真實來源（SSOT）**：
   - 共用 [`../www/.env`](../www/.env)（維持 `MOCK=true`）、[`../www/assets/db/*.db`](../www/assets/db/)、`../www/log/`、`../www/export/` 與 `../www/import/`。
   - **嚴禁在 `API/` 建立獨立 `.env` 或複製 SQLite 資料庫**。
2. **介面契約零破壞**：
   - API 路由、Request 參數、Response JSON 結構與 `STATUS_CODE` 整數狀態碼必須與 `www/` 100% 一致。
3. **保守語法修復**：
   - 僅修正 PHP 8.5 阻斷錯誤（P0）與棄用警告（P1），保留既有業務結構以便與 `www/` 進行雙向 Diff 比對。

---

## 2. PHP 8.5 修正標準（P0 / P1）

### 2.1 P0：阻斷性致命錯誤（Fatal Errors）
- **`SQLite3Stmt::bindParam` 傳址錯誤**：
  - PHP 8.0+ 要求 `bindParam` 第二參數必須為可傳址變數。若傳入運算式或函式回傳值（如 `str_replace(...)`、`date(...)`），**一律改為 `$stm->bindValue(':key', expr);`**。
- **`session_set_cookie_params` 參數規範**：
  - 陣列鍵名強制小寫，布林值不得傳字串：
    ```php
    session_set_cookie_params([
        'samesite' => 'Lax',
        'secure'   => false,
        'httponly' => true
    ]);
    ```

### 2.2 P1：PHP 8.2 ~ 8.5 棄用警告清理（Deprecation Warnings）
- **字串插值**：棄用 `${var}`，一律改為 `{$var}` 或 `{$obj->prop}`。
- **資源釋放函式**：PHP 8.5 棄用 `curl_close($ch)` 與 `imagedestroy($image)`，直接移除或改為 `unset($ch);` / `unset($image);`。
- **使用者錯誤觸發**：PHP 8.4 棄用 `trigger_error(..., E_USER_ERROR)`，改為 `throw new \RuntimeException($message);`。
- **字串編碼轉換**：PHP 8.2 棄用 `utf8_decode()` / `utf8_encode()`，改為 `mb_convert_encoding((string)$val, 'ISO-8859-1', 'UTF-8')`。
- **未定義陣列索引**：存取 `$_POST`、`$_GET` 或外部陣列時一律加上 Null 合併運算子（如 `$_POST['type'] ?? ''`）。

---

## 3. Oracle 9i 雙模連線架構（CLI Bridge）

因現行資料庫為 **Oracle 9i**，PHP 8.5 無法直接載入 `instantclient_11_2`：
1. **雙模自動橋接**：當 `extension_loaded('oci8') === false` 時，[`include/OraDB.class.php`](./include/OraDB.class.php) 自動啟用 `use_cli_bridge`，透過 `proc_open` 呼叫 `C:\AppServ\php7\php.exe` 執行 `ora_cli_worker.php`（業務端具名綁定 `:bv_*` 與 Big5 轉碼零修改）。
2. **未來 Oracle 12c/19c 切換**：只需於 `C:\AppServ\php85\php.ini` 啟用 `php_oci8_19.dll`，`OraDB` 即自動切換為 PHP 8.5 原生直連。

---

## 4. 交付驗證指令

- **PHP 8.5 語法與棄用檢查**：`C:\AppServ\php85\php.exe -l <modified_file>`（確認 0 Fatal Error、0 Deprecated Warning）。
- **端點實機驗證**：`curl.exe -s -X POST -d "type=..." http://127.0.0.1/LAH-API/v1/<endpoint>.php`（確認回傳合規 JSON 且無 PHP Warning/HTML 雜訊）。
