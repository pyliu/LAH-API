<?php
require_once("Logger.class.php");
require_once("System.class.php");
require_once("SQLiteDBFactory.class.php");

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
        if ($this->prepareDB($channel)) {
            // TODO: add message
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'));
            $stm = $db->prepare("
                INSERT INTO message ('title', 'content', 'priority', 'create_datetime', 'expire_datetime', 'sender', 'from_ip', 'flag')
                VALUES (:bv_title, :bv_content, :bv_priority, :bv_create_datetime, :bv_expire_datetime, :bv_sender, :bv_from_ip, :bv_flag)
            ");
            if ($this->bindParams($stm, $row)) {
                return $stm->execute() === FALSE ? false : true;
            }
            return false;
        }
    }


}
