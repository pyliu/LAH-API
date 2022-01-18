<?php
require_once("Logger.class.php");
require_once("System.class.php");
require_once("SQLiteDBFactory.class.php");
require_once("StatsSQLite.class.php");

class Notification {
    
    private $ws_db_path;
    private $ws_share_db_file;

    private function fileExists($channel) {
        return file_exists($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db');
    }

    private function prepareDB($channel) {
        if (!$this->fileExists($channel)) {
            // copy predefined sqlite db
            $res = copy($this->ws_share_db_file, $this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db');
            if (!$res) {
                Logger::getInstance()->error(__METHOD__.': copy message.db failed. ('.$this->ws_share_db_file.' => '.$this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'.')');
                return false;
            }
        }
        return true;
    }

    
    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return false;
        }
        $create_datetime = date('Y-m-d H:i:s');
        $expire_datetime = empty($row['expire_datetime']) ? '' : $row['expire_datetime'];
        $priority = $row['priority'] ?? 3;  // 0-3: critical -> high -> average -> low
        $flag = empty(intval($row['flag'])) ? 0 : intval($row['flag']);
        $stm->bindParam(':bv_title', $row['title']);
        $stm->bindParam(':bv_content', $row['content']);
        $stm->bindParam(':bv_priority', $priority);
        $stm->bindParam(':bv_create_datetime', $create_datetime);
        $stm->bindParam(':bv_expire_datetime', $expire_datetime);
        $stm->bindParam(':bv_sender', $row['sender']);
        $stm->bindParam(':bv_from_ip', $row['from_ip']);
        $stm->bindParam(':bv_flag', $flag);

        return true;
    }

    function __construct() {
        $this->ws_db_path = System::getInstance()->getWSDBPath();
        if (empty($this->ws_db_path)) {
            $this->ws_db_path = dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR.'nuxtjs'.DIRECTORY_SEPARATOR.'ws'.DIRECTORY_SEPARATOR.'db';
        }
        $this->ws_share_db_file = dirname($this->ws_db_path).DIRECTORY_SEPARATOR.'dimension'.DIRECTORY_SEPARATOR.'message.db';
    }

    function __destruct() { }

    public function addMessage($channel, $payload) {
        if (empty($channel) || !is_array($payload) || empty($payload)) {
            Logger::getInstance()->error(__METHOD__.': required param is missing. ('.$channel.')');
            return false;
        }
        if (in_array($channel, array('all', 'hr', 'acc', 'adm', 'reg', 'sur', 'val', 'inf', 'supervisor'))) {
            $channel = $channel === 'all' ? 'announcement' : 'announcement_'.$channel;
        }
        if ($this->prepareDB($channel)) {
            // TODO: add message
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'));
            $stm = $db->prepare("
                INSERT INTO message ('title', 'content', 'priority', 'create_datetime', 'expire_datetime', 'sender', 'from_ip', 'flag')
                VALUES (:bv_title, :bv_content, :bv_priority, :bv_create_datetime, :bv_expire_datetime, :bv_sender, :bv_from_ip, :bv_flag)
            ");
            if ($this->bindParams($stm, $payload)) {
                if ($stm->execute() !== FALSE) {
                    // add stats
                    $stats = new StatsSQLite();
                    $stats->addNotificationCount();
                    // return last inserted id
                    return $db->querySingle("SELECT id from message ORDER BY id DESC LIMIT 1");
                }
                // return $stm->execute() === FALSE ? false : true;
            }
            return false;
        }
    }

    
    public function removeMessage($channel, $payload) {
        // expect payload has id and type info
        if (empty($channel) || empty($payload['type'])) {
            Logger::getInstance()->error(__METHOD__.': required param is missing. ('.$channel.', '.$payload['type'].')');
            return false;
        }
        if (empty($payload['id'])) {
            Logger::getInstance()->warning(__METHOD__.': 沒有指定 id 略過處理本次刪除請求. ('.$channel.', '.$payload['type'].')');
            return true;
        }
        // special handling for announcement
        if ($payload['type'] === 'announcement' && in_array($channel, array('all', 'hr', 'acc', 'adm', 'reg', 'sur', 'val', 'inf', 'supervisor'))) {
            $channel = $channel === 'all' ? 'announcement' : 'announcement_'.$channel;
        }
        Logger::getInstance()->info(__METHOD__.': 準備刪除 '.$channel.' 頻道 '.$payload['id'].' 資料');
        if ($this->prepareDB($channel)) {
            // TODO: add message
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'));
            if ($stm = $db->prepare("DELETE FROM message WHERE id = :bv_id")) {
                $stm->bindParam(':bv_id', $payload['id']);
                return $stm->execute() === FALSE ? false : true;
            }
        }
        return false;
    }
}
