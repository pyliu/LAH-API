<?php
require_once("FileAPICommand.class.php");
class FileAPILogExportCommand extends FileAPICommand {
    private $date;
    function __construct($date) {
        $this->date = $date;
        if (empty($this->date)) {
            $this->date = date('Y-m-d');
        }
    }

    function __destruct() {}

    public function execute() {
        $path = dirname(dirname(__FILE__))."/logs/log-".$this->date.".log";
        // fall back to get today's log
        if (!file_exists($path)) {
            $path = dirname(dirname(__FILE__))."/logs/log-".date('Y-m-d').".log";
        }
        header("Content-Type: text/log");
        $out = fopen("php://output", 'w'); 
        fwrite($out, file_get_contents($path));
        fclose($out);
    }
}
?>
