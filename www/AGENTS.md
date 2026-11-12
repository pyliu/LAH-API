# www/AGENTS.md — PHP 7.3 線上生產軌道維護規範

> **適用範圍**：`LAH-API/www/` 目錄下之 PHP 7.3/7.4 RESTful API、排程與舊版網頁。  
> **雙軌同步鐵律**：凡修改 `www/api/*.php` 或 `www/include/*.class.php`，**必須同步更新 [`../API/v1/*.php`](../API/AGENTS.md) 與 `../API/include/*.class.php`**，確保 FastCGI 新版服務端點一致。

---

## 1. 語言與資料庫相容性紅線

### 1.1 嚴守 PHP 7.3 語法邊界
所有 `www/` 程式碼必須 100% 相容 **PHP 7.3**。嚴禁使用 PHP 7.4+ / 8.x 語法：
- 禁用：`match` 表達式、`enum`、Constructor Property Promotion、Named Arguments、`readonly`、Attributes、Union / `mixed` Types、Nullsafe Operator（`?->`）與 `fn()` Arrow Function。
- 驗證指令：`php -l <modified_file>`（排程驗證：`php www/task/watchdog.php`）。

### 1.2 Oracle 9i 與 SQLite 3 相容性
- **Oracle 9i 限制**：嚴禁使用 `FETCH FIRST / OFFSET`、Oracle 12c+ 語法、JSON SQL 函式、新版 `MERGE` 或反引號 `` ` ``；一律使用 ANSI SQL、Prepared Statement（Bind Variable）與 `TO_DATE` / `TO_CHAR`。
- **雙庫相容**：若類別同時支援 Oracle 9i 與 SQLite 3，修改查詢時必須兼顧兩者索引、排序與 `NULL` 行為差異。
- **強制 MOCK 模式**：開發與驗證期間 `www/.env` 必須維持 `MOCK=true`，嚴禁未授權連入正式 Oracle 9i 執行異動。

---

## 2. REST API 與 `STATUS_CODE` 契約

除非明確要求，**嚴禁修改既有 API URL、Request 參數、Response JSON Key 名稱與資料型別**（Boolean 不得轉字串 `"true"`/`"false"`，Number 不得任意轉 String）。

`status` 欄位**必須為整數（`int`）**，定義於 `STATUS_CODE` 抽象類別常數，嚴禁回傳字串 `"OK"` / `"ERROR"`：

```php
abstract class STATUS_CODE {
    const SUCCESS_WITH_NO_RECORD = 3;        // 成功但無資料
    const SUCCESS_WITH_MULTIPLE_RECORDS = 2; // 成功且多筆資料
    const SUCCESS_NORMAL = 1;                // 一般成功
    const DEFAULT_FAIL = 0;
    const UNSUPPORT_FAIL = -1;
    const FAIL_WITH_LOCAL_NO_RECORD = -2;
    const FAIL_NOT_VALID_SERVER = -3;
    const FAIL_WITH_REMOTE_NO_RECORD = -4;
    const FAIL_NO_AUTHORITY = -5;
    const FAIL_JSON_ENCODE = -6;
    const FAIL_NOT_FOUND = -7;
    const FAIL_LOAD_ERROR = -8;
    const FAIL_TIMEOUT = -9;
    const FAIL_REMOTE_UNREACHABLE = -10;
    const FAIL_DB_ERROR = -11;
}
```

- **正數（1、2、3）代表成功**；**0 與負數代表失敗**（應優先對應明確錯誤常數，如 `FAIL_DB_ERROR`、`FAIL_NO_AUTHORITY`，避免籠統回傳 `DEFAULT_FAIL`）。
- 若需新增失敗常數，沿用遞減負數慣例，嚴禁變更既有常數數值。
- 涉及資料寫入時必須包裹於 Transaction 與 `try/catch` 中，且**嚴禁將原始 Database Exception 直接暴露給前端**。
