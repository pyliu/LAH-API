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
        $stm->bindValue(':message', (string)$row['message'] ?? '');
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

    private function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO mail ('id', 'from', 'to', 'subject', 'message', 'timestamp', 'mailbox')
            VALUES (:id, :from, :to, :subject, :message, :timestamp, :mailbox)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
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

    public function fetchFromMailServer() {
        // check if mail server is reachable
        $mail_host = System::getInstance()->get('MONITOR_MAIL_HOST');
        $latency = pingDomain($mail_host, 143);
    
        // not reachable
        if ($latency > 999 || $latency == '') {
            Logger::getInstance()->error(__METHOD__.': 無法連線郵件伺服器 '.$mail_host.'，無法擷取監控郵件。');
            return false;
        }

        $latest_id = 0;
        $latest_mail = $this->getLatestMail();
        if ($latest_mail) {
            $latest_id = $latest_mail["id"];
        }
        
        Logger::getInstance()->info(__METHOD__.':目前最新郵件 id 為 '.$latest_id);
        
        $monitor = new MonitorMail();
        $mails = $monitor->getAllMails();
        $inserted = 0;
        foreach($mails as $mail) {
            if ($mail["id"] > $latest_id) {
                $this->replace($mail);
                $inserted++;
            }
        }

        Logger::getInstance()->info(__METHOD__.':已擷取 '.$inserted.' 封監控郵件。');
        
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
    public function getLatestMail() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM mail ORDER BY id DESC LIMIT 1")) {
            return $this->prepareArray($stmt)[0];
        }
        return false;
    }
    /**
     * 取得最近區間郵件
     */
    public function getMailsWithinSeconds($seconds_before = 15 * 60) {
        $ts = time() - intval($seconds_before);
        if($stmt = $this->db->prepare("SELECT * FROM mail WHERE timestamp >= $ts ORDER BY id DESC")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得郵件 BY 搜尋 subject 關鍵字
     */
    public function getMailsBySubject($query_string, $seconds_before = 24 * 60 * 60) {
        $ts = time() - intval($seconds_before);
        if($stmt = $this->db->prepare("SELECT * FROM mail WHERE timestamp >= $ts AND subject LIKE '%$query_string%' ORDER BY timestamp DESC")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
}
