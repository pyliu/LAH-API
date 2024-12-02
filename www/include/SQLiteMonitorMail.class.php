<?php
require_once('init.php');
require_once('System.class.php');
require_once('MonitorMail.class.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteMonitorMail {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':id', $row['id']);
        $stm->bindParam(':from', $row['from']);
        $stm->bindParam(':to', $row['to']);
        $stm->bindParam(':subject', $row['subject']);
        $stm->bindParam(':message', $row['message']);
        $stm->bindParam(':timestamp', $row['timestamp']);
        $stm->bindParam(':mailbox', $row['mailbox']);
    }

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    function __construct() {
        $db_path = SQLiteDBFactory::getMonitorMailDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO mail ('id', 'sender', 'receiver', 'subject', 'message', 'timestamp', 'mailbox')
            VALUES (:id, :from, :to, :subject, :message, :timestamp, :mailbox)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }

    public function fetchFromMailServer() {
        // check if mail server is reachable
        $mail_host = System::getInstance()->get('MONITOR_MAIL_HOST');
        $mail_ssl = System::getInstance()->get('MONITOR_MAIL_SSL') === 'true';
        // IMAP 993/143
        $latency = pingDomain($mail_host, $mail_ssl ? 993 : 143);
        // POP3 995/110
        // $latency = pingDomain($mail_host, $mail_ssl ? 995 : 110);
        // not reachable
        if ($latency > 999 || $latency == '') {
            Logger::getInstance()->error(__METHOD__.': 無法連線郵件伺服器 '.$mail_host.'，無法擷取監控郵件。');
            return false;
        }

        // $latest_id = 0;
        // $latest_mail = $this->getLatestMail();
        // if ($latest_mail) {
        //     $latest_id = $latest_mail["id"];
        // }
        
        // Logger::getInstance()->info(__METHOD__.':目前最新郵件 id 為 '.$latest_id);
        
        $monitor = new MonitorMail();
        $mails = $monitor->getAllUnseenMails('INBOX', 1);  // INBOX, wthin 1 day
        $inserted = 0;
        $failed = 0;
        foreach($mails as $mail) {
            // if ($mail["id"] > $latest_id) {
                $result = $this->replace($mail);

                $retry = 0;
                while ($result === false && $retry < 3) {
                    // like TCP congestion retry delay ... 
                    $zzz_us = random_int(100000, 500000) * pow(2, $retry);
                    Logger::getInstance()->warning(__METHOD__.": 寫入 MonitorMail 失敗 ".number_format($zzz_us / 1000000, 3)." 秒後重試。(".($retry + 1).")");
                    usleep($zzz_us);
                    $retry++;
                    $result = $this->replace($mail);
                }
                
                if ($result) {
                    $inserted++;
                    // extracted from server, mark it as \Seen ...
                    $monitor->markMailAsRead($mail['id']);
                } else {
                    $failed++;
                    Logger::getInstance()->warning(__METHOD__.': 插入監控郵件資料庫失敗。');
                    Logger::getInstance()->info(__METHOD__.': payload => '.print_r($mail, true));
                }
            // }
        }
        // clean mails on the server
        // $monitor->expungeDeletedMails();
        Logger::getInstance()->info(__METHOD__.': 已擷取 '.$inserted.' 封監控郵件。(失敗: '.$failed.')');
        
        return $inserted;
    }

    public function exists($id) {
        $ret = $this->db->querySingle("SELECT id from mail WHERE id = '$id'");
        return !empty($ret);
    }

    public function clean() {
        $stm = $this->db->prepare("DELETE FROM mail");
        return $stm->execute() === FALSE ? false : true;
    }

    /**
     * 取得最新郵件
     */
    public function getLatestMail($convert = false) {
        // $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM mail ORDER BY id DESC LIMIT 1")) {
            return $convert ? mb_convert_encoding($this->prepareArray($stmt)[0], 'UTF-8', 'BIG5') : $this->prepareArray($stmt)[0];
        }
        return false;
    }
    /**
     * 取得最新API郵件ID
     */
    public function getNextAPIMailID() {
        $id = $this->db->querySingle("SELECT id from mail WHERE id < 0 ORDER BY id LIMIT 1");
        return intval($id) - 1;
    }
    /**
     * 取得最近區間郵件
     */
    public function getMailsWithinSeconds($seconds_before = 15 * 60, $convert = false) {
        $ts = time() - intval($seconds_before);
        if($stmt = $this->db->prepare("SELECT * FROM mail WHERE timestamp >= $ts ORDER BY id DESC")) {
            return $convert ? mb_convert_encoding($this->prepareArray($stmt), 'UTF-8', 'BIG5') : $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 移除區間外郵件(default is one month)
     */
    public function removeOutdatedMail($seconds_before = 30 * 24 * 60 * 60) {
        $ts = time() - intval($seconds_before);
        $sql = "DELETE FROM mail WHERE timestamp <= :ts";
        if ($stmt = $this->db->prepare($sql)) {
            $stmt->bindParam(":ts", $ts);
            return $stmt->execute() === FALSE ? false : true;
        }
        Logger::getInstance()->warning(__METHOD__.": 無法執行 「".$sql."」 SQL描述。");
        return false;
    }
    /**
     * 取得郵件 BY 搜尋 subject
     */
    public function getMailsBySubject($query_string, $seconds_before = 24 * 60 * 60, $convert = false) {
        $ts = time() - intval($seconds_before);
        if($stmt = $this->db->prepare("SELECT * FROM mail WHERE timestamp >= $ts AND subject LIKE '%$query_string%' ORDER BY timestamp DESC")) {
            return $convert ? mb_convert_encoding($this->prepareArray($stmt), 'UTF-8', 'BIG5') : $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得郵件 BY 搜尋 sender
     */
    public function getMailsBySender($query_string, $seconds_before = 24 * 60 * 60, $convert = false) {
        $ts = time() - intval($seconds_before);
        if($stmt = $this->db->prepare("SELECT * FROM mail WHERE timestamp >= $ts AND sender LIKE '%$query_string%' ORDER BY timestamp DESC")) {
            return $convert ? mb_convert_encoding($this->prepareArray($stmt), 'UTF-8', 'BIG5') : $this->prepareArray($stmt);
        }
        return false;
    }
}
