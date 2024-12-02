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

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        if ($result) {
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
        } else {
            Logger::getInstance()->warning(__CLASS__."::".__METHOD__.": execute SQL unsuccessfully.");
        }
        return $return;
    }

    function __construct() {
        $this->ws_db_path = System::getInstance()->getWSDBPath();
        $this->ws_share_db_file = dirname($this->ws_db_path).DIRECTORY_SEPARATOR.'dimension'.DIRECTORY_SEPARATOR.'message.db';
        if (!is_dir($this->ws_db_path) || !is_file($this->ws_share_db_file)) {
            Logger::getInstance()->info('ws_db_path: '.$this->ws_db_path.", ws_share_db_file: ".$this->ws_share_db_file);
            Logger::getInstance()->warning(__CLASS__.': 即時通DB設定參數錯誤，改用預設值！');
            // use default lah-messenger-server path
            $defaultNotificationDBPath = dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR.'lah-messenger-server'.DIRECTORY_SEPARATOR.'db';
            $defaultNotificationMessageFile = dirname($defaultNotificationDBPath).DIRECTORY_SEPARATOR.'dimension'.DIRECTORY_SEPARATOR.'message.db';
            Logger::getInstance()->info('ws_db_path: '.$defaultNotificationDBPath.", ws_share_db_file: ".$defaultNotificationMessageFile);
            $this->ws_db_path = $defaultNotificationDBPath;
            $this->ws_share_db_file = $defaultNotificationMessageFile;
        }
        $this->ws_share_db_file = dirname($this->ws_db_path).DIRECTORY_SEPARATOR.'dimension'.DIRECTORY_SEPARATOR.'message.db';
    }

    function __destruct() { }

    public function addMessage($channel, $payload, $skip_announcement_convert = false) {
        if (empty($channel) || !is_array($payload) || empty($payload)) {
            Logger::getInstance()->error(__METHOD__.': required param is missing. ('.$channel.')');
            return false;
        }
        // 公告頻道轉換
        if (!$skip_announcement_convert && in_array($channel, array('all', 'hr', 'acc', 'adm', 'reg', 'sur', 'val', 'inf', 'supervisor'))) {
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
                    // return last inserted id
                    return $db->querySingle("SELECT id from message ORDER BY id DESC LIMIT 1");
                }
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

    public function getMessages($channel, $top = 10) {
        $channelDBPath = $this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db';
        if (!file_exists($channelDBPath)) {
            Logger::getInstance()->error(__METHOD__.': DB檔案路徑有誤('.$channelDBPath.')有誤，無法取得訊息。');
            return false;
        }
        if (!is_numeric($top) || intval($top) < 1 ) {
            Logger::getInstance()->warning(__METHOD__.': $top 變數('.$top.')有誤，改用預設值 $top = 10');
            $top = 10;
        }
        // get messages
        if ($this->prepareDB($channel)) {
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($channelDBPath));
            $stm = $db->prepare("SELECT * FROM message ORDER BY id DESC LIMIT :bv_top");
            $stm->bindParam(':bv_top', $top);
            return $this->prepareArray($stm);
        }
        return false;
    }

    public function getMessagesBefore($channel, $before, $limit) {
        $channelDBPath = $this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db';
        if (!file_exists($channelDBPath)) {
            Logger::getInstance()->error(__METHOD__.': DB檔案路徑有誤('.$channelDBPath.')有誤，無法取得訊息。');
            return false;
        }
        if (!is_numeric($limit) || intval($limit) < 1 ) {
            Logger::getInstance()->warning(__METHOD__.': $limit 變數('.$limit.')有誤，改用預設值 $limit = 10');
            $limit = 10;
        }
        // get messages
        if ($this->prepareDB($channel)) {
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($channelDBPath));
            $stm = $db->prepare("SELECT * FROM message WHERE id < :bv_before ORDER BY id DESC LIMIT :bv_limit");
            $stm->bindParam(':bv_before', $before);
            $stm->bindParam(':bv_limit', $limit);
            return $this->prepareArray($stm);
        }
        return false;
    }

    public function getMessageByDuration($channel, $payload) {
        if (is_array($payload)) {
            Logger::getInstance()->warning(__METHOD__.': 查詢的參數應為陣列!');
            Logger::getInstance()->info(__METHOD__.': $payload => '.$payload);
            return false;
        }
        if (!array_key_exists('st', $payload) || !array_key_exists('ed', $payload) || $payload['st'] > $payload['ed']) {
            Logger::getInstance()->warning(__METHOD__.': 查詢的參數錯誤');
            Logger::getInstance()->info(__METHOD__.': $payload => '.$payload);
            return false;
        }
        // get messages
        if ($this->prepareDB($channel)) {
            // TODO: add message
            // $db = new SQLite3(SQLiteDBFactory::getMessageDB($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'));
            // $stm = $db->prepare("
            //     INSERT INTO message ('title', 'content', 'priority', 'create_datetime', 'expire_datetime', 'sender', 'from_ip', 'flag')
            //     VALUES (:bv_title, :bv_content, :bv_priority, :bv_create_datetime, :bv_expire_datetime, :bv_sender, :bv_from_ip, :bv_flag)
            // ");
            // if ($this->bindParams($stm, $payload)) {
            //     if ($stm->execute() !== FALSE) {
            //         // return last inserted id
            //         return $db->querySingle("SELECT id from message ORDER BY id DESC LIMIT 1");
            //     }
            // }
            return false;
        }
        $messages = array();
        return $messages;
    }

    public function removeTodayOfficeDownMessage($channel = 'lds') {
        Logger::getInstance()->info(__METHOD__.': 準備刪除 '.$channel.' 頻道今日地所離線訊息');
        if ($this->prepareDB($channel)) {
            $today = date("Y-m-d");
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'));
            if ($stm = $db->prepare("DELETE FROM message WHERE title = :bv_title and create_datetime like :bv_create_datetime")) {
                $stm->bindValue(':bv_title', '地政系統跨域服務監測');
                $stm->bindValue(':bv_create_datetime', $today.'%');
                return $stm->execute() === FALSE ? false : true;
            }
        }
        return false;
    }

    public function removeOutdatedMessageByTitle($channel, $title) {
        Logger::getInstance()->info(__METHOD__.': 準備刪除「'.$channel.'」頻道標題為「'.$title.'」的訊息');
        if ($this->prepareDB($channel)) {
            $db = new SQLite3(SQLiteDBFactory::getMessageDB($this->ws_db_path.DIRECTORY_SEPARATOR.$channel.'.db'));
            if ($stm = $db->prepare("DELETE FROM message WHERE title = :bv_title")) {
                $stm->bindParam(':bv_title', $title);
                return $stm->execute() === FALSE ? false : true;
            }
        }
        return false;
    }
}
