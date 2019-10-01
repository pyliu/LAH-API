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
        $path = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs".DIRECTORY_SEPARATOR."log-".$this->date.".log";
        $data = null;
        if (file_exists($path)) {
            $data = file_get_contents($path);
        } else {
            $zippath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs".DIRECTORY_SEPARATOR."log-".$this->date.".zip";
            if (file_exists($zippath)) {
                // extract the log for downloading
                $zip = new ZipArchive($zippath);
                if ($zip->open($zippath)) {
                    $zip->extractTo(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs");
                    $zip->close();
                    $data = file_get_contents($path);
                    @unlink($path);
                }
            } else {
                // fall back to get today's log
                $path = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs".DIRECTORY_SEPARATOR."log-".date('Y-m-d').".log";
                $data = file_get_contents($path);
            }
        }

        header("Content-Type: text/log");
        $out = fopen("php://output", 'w'); 
        fwrite($out, $data);
        fclose($out);
    }
}
?>
