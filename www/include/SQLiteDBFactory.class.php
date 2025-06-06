<?php
require_once('init.php');
require_once('DynamicSQLite.class.php');

class SQLiteDBFactory {
    private static $db_folder = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db";

    public static function getPrefetchDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."prefetch.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "cache" (
                "key"	TEXT,
                "value"	TEXT,
                "expire"	INTEGER NOT NULL DEFAULT 864000,
                PRIMARY KEY("key")
            )
        ');
        return $path;
    }

    public static function getMonitorMailDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."monitor_mail.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "mail" (
                "id"	INTEGER NOT NULL,
                "sender"	TEXT NOT NULL,
                "receiver"	TEXT NOT NULL,
                "subject"	TEXT NOT NULL,
                "message"	TEXT,
                "mailbox"	TEXT NOT NULL DEFAULT \'INBOX\',
                "timestamp"	INTEGER NOT NULL,
                PRIMARY KEY("id")
            )
        ');
        return $path;
    }

    public static function getRegForeignerRestrictionDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."reg_foreigner_restriction.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "reg_foreigner_restriction" (
                "pkey"	TEXT NOT NULL,
                "nation"   TEXT,
                "reg_date" TEXT,
                "reg_caseno" TEXT,
                "transfer_date" TEXT,
                "transfer_caseno" TEXT,
                "transfer_local_date" TEXT,
                "transfer_local_principle" TEXT,
                "restore_local_date" TEXT,
                "use_partition" TEXT,
                "control" TEXT,
                "logout" TEXT,
                "note" TEXT,
                PRIMARY KEY("pkey")
            )
        ');
        return $path;
    }

    public static function getRegForeignerPDFDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."reg_foreigner_pdf.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "reg_foreigner_pdf" (
                "id"	INTEGER NOT NULL,
                "year"   TEXT NOT NULL,
                "number"   TEXT NOT NULL,
                "fid"   TEXT NOT NULL,
                "fname" TEXT NOT NULL,
                "note"	TEXT,
                "createtime"	INTEGER NOT NULL,
                "modifytime"	INTEGER,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $path;
    }

    public static function getAdmReserveFilePDFDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."adm_reserve_file_pdf.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "adm_reserve_file_pdf" (
                "id"	INTEGER NOT NULL,
                "number"   TEXT NOT NULL UNIQUE,
                "pid"   TEXT NOT NULL,
                "pname" TEXT NOT NULL,
                "note"	TEXT,
                "createtime"	INTEGER NOT NULL,
                "endtime"	INTEGER,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $path;
    }
    
    public static function getSurDestructionTrackingDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."sur_destruction_tracking.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "sur_destruction_tracking" (
                "id"	INTEGER,
                "number"	TEXT(10) NOT NULL UNIQUE,
                "section_code"	TEXT(4),
                "land_number"	TEXT(100),
                "building_number"	TEXT(100),
                "issue_date"	TEXT(7) NOT NULL,
                "apply_date"	TEXT(7),
                "address"	TEXT(200),
                "occupancy_permit"	TEXT(100),
                "construction_permit"	TEXT(100),
                "note"	TEXT(2000),
                "done"	TEXT(5) NOT NULL DEFAULT "false",
                "updatetime"    INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $path;
    }
    
    public static function getImageDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."image.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "image" (
                "id"	INTEGER NOT NULL,
                "name"	TEXT NOT NULL,
                "path"	TEXT,
                "data"	BLOB,
                "iana"	TEXT DEFAULT "image/jpeg",
                "size"	INTEGER DEFAULT 0,
                "timestamp"	INTEGER NOT NULL,
                "note"	TEXT,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $path;
    }

    public static function getMessageDB($path) {
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "message" (
                "id"	INTEGER,
                "title"	TEXT,
                "content"	TEXT NOT NULL,
                "priority"	INTEGER NOT NULL DEFAULT 3,
                "create_datetime"	TEXT NOT NULL,
                "expire_datetime"	TEXT,
                "sender"	TEXT NOT NULL,
                "from_ip"	TEXT,
                "flag"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $path;
    }

    public static function getIPResolverDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."IPResolver.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "IPResolver" (
                "ip" TEXT NOT NULL,
                "added_type" TEXT NOT NULL DEFAULT \'DYNAMIC\',
                "entry_type" TEXT NOT NULL DEFAULT \'USER\',
                "entry_desc" TEXT NOT NULL,
                "entry_id" TEXT,
                "timestamp" NUMERIC NOT NULL,
                "note" TEXT,
                PRIMARY KEY("ip", "added_type", "entry_type")
            )
        ');
        return $path;
    }

    public static function getRKEYNALLDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."RKEYN_ALL.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "RKEYN_ALL" (
                "KCDE_1"	TEXT NOT NULL,
                "KCDE_2"	TEXT NOT NULL,
                "KCDE_3"	TEXT NOT NULL,
                "KCDE_4"	TEXT,
                "KNAME"	TEXT NOT NULL,
                "KRMK"	TEXT
            )
        ');
        return $path;
    }

    public static function getRKEYNDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."RKEYN.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "RKEYN" (
                "KCDE_1"	TEXT NOT NULL,
                "KCDE_2"	TEXT NOT NULL,
                "KCNT"	TEXT NOT NULL,
                "KRMK"	TEXT,
                PRIMARY KEY("KCDE_1", "KCDE_2")
            )
        ');
        return $path;
    }

    public static function getOFFICESDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."OFFICES.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "OFFICES" (
                "ID"	TEXT NOT NULL,
                "NAME"	TEXT NOT NULL,
                "ALIAS"	TEXT NOT NULL,
                PRIMARY KEY("ID")
            )
        ');
        return $path;
    }

    public static function getOFFICESSTATSDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."OFFICES_STATS.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "OFFICES_STATS" (
                "serial"	INTEGER,
                "id"	TEXT NOT NULL,
                "name"	TEXT,
                "state"	TEXT NOT NULL DEFAULT \'DOWN\',
                "response"	TEXT,
                "timestamp"	INTEGER NOT NULL,
                PRIMARY KEY("serial" AUTOINCREMENT)
            )
        ');
        return $path;
    }

    public static function getCaseCodeDB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."CaseCode.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "CaseCode" (
                "KCDE_2"	TEXT NOT NULL,
                "KCNT"	TEXT NOT NULL,
                "KRMK"	TEXT,
                PRIMARY KEY("KCDE_2")
            )
        ');
        return $path;
    }

    public static function getRegUntakenStoreDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'reg_untaken_store.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "reg_untaken_store" (
                "case_no"	TEXT,
                "taken_date" TEXT,
                "taken_status" TEXT,
                "lent_date" TEXT,
                "return_date" TEXT,
                "borrower" TEXT,
                "note"	TEXT,
                PRIMARY KEY("case_no")
            )
        ');
        return $db_path;
    }

    public static function getValRealpriceMemoStoreDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'val_realprice_memo_store.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "val_realprice_memo_store" (
                "id"	INTEGER,
                "case_no"	TEXT UNIQUE,
                "declare_date"	TEXT,
                "declare_note"	TEXT,
                "timestamp"	INTEGER,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $db_path;
    }

    public static function getRegAuthChecksStoreDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'reg_auth_checks_store.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "reg_auth_checks_store" (
                "case_no"	TEXT,
                "authority"	INTEGER NOT NULL DEFAULT 0,
                "note"	TEXT,
                PRIMARY KEY("case_no")
            )
        ');
        return $db_path;
    }

    public static function getRegFixCaseStoreDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'reg_fix_case_store.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "reg_fix_case_store" (
                "case_no"	TEXT,
                "fix_deadline_date"	TEXT,
                "notify_delivered_date"	TEXT,
                "note"	TEXT,
                PRIMARY KEY("case_no")
            )
        ');
        return $db_path;
    }

    public static function getSYSAUTH1DB() {
        $path = SQLiteDBFactory::$db_folder.DIRECTORY_SEPARATOR."SYSAUTH1.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "SYSAUTH1" (
                "USER_ID"	TEXT NOT NULL,
                "USER_PSW"	TEXT,
                "USER_NAME"	TEXT NOT NULL,
                "GROUP_ID"	INTEGER,
                "VALID"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("USER_ID")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "SYSAUTH1_ALL" (
                "USER_ID"	TEXT NOT NULL,
                "USER_NAME"	TEXT,
                PRIMARY KEY("USER_ID")
            )
        ');
        return $path;
    }

    public static function getLAHDB() {
        $db_path = DEF_SQLITE_DB;
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "overdue_stats_detail" (
                "datetime"	TEXT NOT NULL,
                "id"	TEXT NOT NULL,
                "count"	NUMERIC NOT NULL DEFAULT 0,
                "note"	TEXT,
                PRIMARY KEY("id","datetime")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "stats" (
                "ID"	TEXT,
                "NAME"	TEXT NOT NULL,
                "TOTAL"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("ID")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "stats_raw_data" (
                "id"	TEXT NOT NULL,
                "data"	TEXT,
                PRIMARY KEY("id")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "xcase_stats" (
                "datetime"	TEXT NOT NULL,
                "found"	INTEGER NOT NULL DEFAULT 0,
                "note"	TEXT,
                PRIMARY KEY("datetime")
            )
        ');
        return $db_path;
    }

    public static function getAPConnStatsDB($ip_end) {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'stats_ap_conn_AP'.$ip_end.'.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "ap_conn_history" (
                "log_time"	TEXT NOT NULL,
                "ap_ip"	TEXT NOT NULL,
                "est_ip"	TEXT NOT NULL,
                "count"	INTEGER NOT NULL DEFAULT 0,
                "batch"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("log_time","ap_ip","est_ip")
            )
        ');
        return $db_path;
    }
    
    public static function getConnectivityDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR."connectivity.db";
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "connectivity" (
                "log_time"	TEXT NOT NULL,
                "target_ip"	TEXT NOT NULL,
                "status"    TEXT NOT NULL DEFAULT \'DOWN\',
                "latency"	REAL NOT NULL DEFAULT 0.0,
                PRIMARY KEY("log_time","target_ip")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "target" (
                "ip"	TEXT NOT NULL,
                "port"	INTEGER,
                "name"	TEXT NOT NULL,
                "monitor"	TEXT NOT NULL DEFAULT \'Y\',
                "note"	TEXT,
                PRIMARY KEY("ip")
            )
        ');
        return $db_path;
    }

    public static function getAPConnectionHistoryDB($ip) {
        $postfix = $ip;
        if (stripos($ip, '.') !== false) {
            $postfix = explode('.', $ip)[3];
        }
        $db_path = DB_DIR.DIRECTORY_SEPARATOR."stats_ap_conn_AP".$postfix.".db";
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "ap_conn_history" (
                "log_time"	TEXT NOT NULL,
                "ap_ip"	TEXT NOT NULL,
                "est_ip"	TEXT NOT NULL,
                "count"	INTEGER NOT NULL DEFAULT 0,
                "batch"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("log_time","ap_ip","est_ip")
            )
        ');
        return $db_path;
    }

    public static function getAdminActionLogDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR."admin_action_log.db";
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "admin_action_log" (
                "id"	INTEGER,
                "ip"	TEXT(15) NOT NULL,
                "timestamp"	INTEGER NOT NULL,
                "action"	TEXT(100),
                "path"	TEXT(100),
                "note"	TEXT,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $db_path;
    }

    public static function getNotificationLogDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR."notification_log.db";
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "notification_log" (
                "id"	INTEGER,
                "channel" TEXT,
                "title"	TEXT,
                "content"	TEXT NOT NULL,
                "priority"	INTEGER NOT NULL DEFAULT 3,
                "create_datetime"	TEXT NOT NULL,
                "expire_datetime"	TEXT,
                "sender"	TEXT NOT NULL,
                "from_ip"	TEXT,
                "flag"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $db_path;
    }

    public static function getSMSLogDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR."sms_moicas_ma05_log.db";
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "MOICAS_MA05_LOG" (
                "id"	INTEGER,
                "MA5_NO" TEXT NOT NULL UNIQUE,
                "MOBILE"	TEXT NOT NULL,
                "MESSAGE"	TEXT,
                "COUNT"	INTEGER DEFAULT 0,
                "NOTE"	TEXT,
                "TIMESTAMP"	INTEGER NOT NULL,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $db_path;
    }

    private function __construct() {}
    // private because of singleton
    private function __clone() { }
    function __destruct() {}
}
