<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRegForeignerPDF {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": 無法綁定變數 \$stm is false.");
            return false;
        }

        if (!file_exists($row['path'])) {
            Logger::getInstance()->error(__METHOD__.": ".$row['path']);
            Logger::getInstance()->error(__METHOD__.": 檔案不存在，無法綁定BLOB資料。");
            return false;
        }

        $stm->bindParam(':name', $row['name']);
        $stm->bindParam(':path', $row['path']);
        $stm->bindValue(':data', file_get_contents($row['path']), SQLITE3_BLOB);
        $stm->bindValue(':iana', mime_content_type($row['path']));
        $stm->bindValue(':size', filesize($row['path']));
        $stm->bindValue(':timestamp', time());
        $stm->bindParam(':note', $row['note']);

        return true;
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
        $this->db = new SQLite3(SQLiteDBFactory::getRegForeignerPDFDB());
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function getLastInsertedId() {
        return $this->db->lastInsertRowID();
    }

    public function exists($path) {
        $id = $this->db->querySingle("SELECT id from image WHERE path = '$path'");
        if (!$id) {
            $id = $this->db->querySingle("SELECT id from image WHERE name = '$path'");
        }
        return $id;
    }

    public function addImage($post) {
        $id = $this->exists($post['path']);
        if ($id) {
            if (!file_exists($post['path'])) {
                Logger::getInstance()->error(__METHOD__.": ".$post['path']);
                Logger::getInstance()->error(__METHOD__.": 存放檔案不存在，無法更新BLOB資料。");
                return false;
            }
            Logger::getInstance()->warning(__METHOD__.": 影像資料已存在，將更新BLOB資料。(id: $id)");
            $stm = $this->db->prepare("UPDATE image SET data = :data, note = :note, size = :size, iana = :iana WHERE id = :id");
            $stm->bindParam(':id', $id);
            $stm->bindParam(':note', $post['note']);
            $stm->bindValue(':data', file_get_contents($post['path']), SQLITE3_BLOB);
            $stm->bindParam(':iana', mime_content_type($post['path']));
            $stm->bindParam(':size', filesize($post['path']));
            return $stm->execute() === FALSE ? false : $id;
        } else {
            $stm = $this->db->prepare("
                INSERT INTO image ('name', 'path', 'data', 'iana', 'size', 'timestamp', 'note')
                VALUES (:name, :path, :data, :iana, :size, :timestamp, :note)
            ");
            if ($this->bindParams($stm, $post)) {
                return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
            }
        }
        return false;
    }

    public function getImageData($name_or_path) {
        $id = $this->db->querySingle("SELECT id from image WHERE name = '$name_or_path' ORDER BY timestamp DESC");
        if (!$id) {
            $id = $this->db->querySingle("SELECT id from image WHERE path = '$name_or_path' ORDER BY timestamp DESC");
        }
        if($stmt = $this->db->prepare("SELECT * FROM image WHERE id = :bv_id")) {
            $stmt->bindParam(':bv_id', $id);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM image WHERE id = '${id}'」SQL描述。");
        }
        return array();
    }

    public function getImageByFilename($filename) {
        $id = $this->db->querySingle("SELECT id from image WHERE name = '$filename' ORDER BY timestamp DESC");
        if ($id) {
            return $this->getImageById($id);
        }
        return false;
    }

    public function getImageByPath($path) {
        $id = $this->db->querySingle("SELECT id from image WHERE path = '$path' ORDER BY timestamp DESC");
        if ($id) {
            return $this->getImageById($id);
        }
        return false;
    }

    public function getImageById($id) {
        $stream = $this->db->openBlob('image', 'data', $id);
        $binary = stream_get_contents($stream);
        fclose($stream); // mandatory, otherwise the next line would fail
        return $binary;
    }

    public function getImages($threadhold = 604800) {
        /**
         * default get entry within a year
         * a year: 31556926
         * a month: 2629743
         * a week: 604800
         **/
        $ondemand = time() - $threadhold;
        if($stmt = $this->db->prepare("SELECT * FROM image WHERE timestamp > :bv_ondemand ORDER BY timestamp DESC")) {
            $stmt->bindParam(':bv_ondemand', $ondemand);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM image WHERE timestamp > $ondemand ORDER BY timestamp DESC」SQL描述。");
        }
        return array();
    }
}
