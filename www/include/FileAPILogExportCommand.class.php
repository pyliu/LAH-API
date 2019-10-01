<?php
require_once("GlobalFunctions.inc.php");
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
        global $log;
        $path = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs".DIRECTORY_SEPARATOR."log-".$this->date.".log";
        if (!file_exists($path)) {
            $zippath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs".DIRECTORY_SEPARATOR."log-".$this->date.".zip";
            if (file_exists($zippath)) {
                $log->info("extract the zipped log for downloading.【${zippath}】");
                // extract the log for downloading
                $zip = new ZipArchive($zippath);
                if ($zip->open($zippath)) {
                    $zip->extractTo(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs");
                    $zip->close();
                }
            } else {
                // fall back to get today's log
                $path = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs".DIRECTORY_SEPARATOR."log-".date('Y-m-d').".log";
            }
        }

        header("Content-Type: text/log");
        $out = fopen("php://output", 'w'); 
        fwrite($out, file_get_contents($path));
        fclose($out);

        // zip other logs after downloading
        zipLogs();
    }
}
?>
