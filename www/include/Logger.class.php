<?php
/** 
* st-PHP-Logger - Simple PHP logging class. Log info, warning, error messages to log files.
* $time = date('Y-M-d');
* $log = Logger::getInstance('log/log-' . $time . '.txt');
* $log->warning('this is the warning message');
* $log->info('this is the info message');
*/
/**
* @author  Drew D. Lenhart - snowytech
* @since   May 29, 2016
* @link    https://github.com/snowytech/st-php-logger
* @version 1.0.0
*/

class Logger {
    // singleton
    private static $_instance = null;
    public static function getInstance($log_file = '', $params = array()) {
        if (!(self::$_instance instanceof Logger)) {
            self::$_instance = new Logger($log_file, $params);
        }
        return self::$_instance;
    }

    /**
    * $log_dir - dir of logs
    * @var string
    */
    protected $log_dir;

    /**
    * $log_file - path and log file name
    * @var string
    */
    protected $log_file;

    /**
    * $file - file
    * @var string
    */
    protected $file;

    /**
    * $options - settable options - future use - passed through constructor
    * @var array
    */
    protected $options = array(
        'dateFormat' => 'Y-m-d H:i:s'
    );

    /**
    * Class constructor
    * @param string $log_file - path and filename of log
    * @param array $params
    */
    private function __construct($log_file = '', $params = array()){
        $this->log_dir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."log";
        if (empty($log_file)) {
            // ex: log-2019-09-16.log
            $log_file = $this->log_dir.DIRECTORY_SEPARATOR.'log-'.date('Y-m-d').'.log';
        }
        $this->log_file = $log_file;
        $this->options = array_merge($this->options, $params);

        //Create log file if it doesn't exist.
        if(!file_exists($log_file)){               
            fopen($log_file, 'w') or exit("無法建立 ${log_file}！");
        }

        //Check permissions of file.
        if(!is_writable($log_file)){   
            //throw exception if not writable
            throw new Exception("ERROR: 無法寫入檔案 ${log_file}", 1);
        }
    }

    // private because of singleton
    private function __clone() { }

    /**
    * Zip method (zip the log file)
    * @param string $date
    * @return void
    */
    public function zip($date){
        if (!preg_match("/^[[:digit:]]{4}\-[[:digit:]]{2}\-[[:digit:]]{2}$/", $date)) {
            $this->error("The input date format is wrong! ($date, correct param is like '2019-10-01')");
            return false;
        }

        $today = date("Y-m-d");
        if ($date == $today) {
            $this->warning("We should not zip today's log! Skip compression operation.");
            return false;
        }

        // Enter the name to creating zipped directory
        $zipcreated = "log-${date}.zip";
        $zip_file = $this->log_dir.DIRECTORY_SEPARATOR.$zipcreated;
        $log_file = "log-${date}.log";
        $log_path = $this->log_dir.DIRECTORY_SEPARATOR.$log_file;

        if (!file_exists($log_path)) {
            $this->error("log file doesn't exists! 【${log_path}】");
            return false;
        }

        $zip = new ZipArchive(); 
        if($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) { 
            if(is_file($log_path)) { 
                $zip->addFile($log_path, $log_file);
            }
            $zip->close();
            @unlink($log_path);
            $this->info("$log_file removed.");
        } 

        return true;
    }
    /**
     * Remove outdated log file
     */
    public function removeOutdatedLog($seconds_before = 30 * 24 * 60 * 60) {
        $ts = time() - $seconds_before;
        $this->info("🕓 開始移除 ".date("Y-m-d H:i:s", $ts)." 之前的紀錄檔案。");
        // Assigning files inside the directory
        $dir = new RecursiveDirectoryIterator($this->log_dir, FilesystemIterator::SKIP_DOTS | RecursiveIteratorIterator::CHILD_FIRST);
        // Removing directories and files inside the specified folder
        foreach ($dir as $file) { 
            if ($file->isFile() && $file->getMTime() <= $ts) {
                $this->info("移除".$file->getFilename());
                @unlink($file);
            }
        }
        $this->info("✔ 移除 ".date("Y-m-d H:i:s", $ts)." 之前的紀錄檔案已完成。");
    }
    /**
    * Info method (write info message)
    * @param string $message
    * @return void
    */
    public function info($message){
        $this->writeLog($message, 'INFO');
    }

    /**
    * Debug method (write debug message)
    * @param string $message
    * @return void
    */
    public function debug($message){
        $this->writeLog($message, 'DEBUG');
    }

    /**
    * Warning method (write warning message)
    * @param string $message
    * @return void
    */
    public function warning($message){
        $this->writeLog($message, 'WARNING');	
    }

    /**
    * Error method (write error message)
    * @param string $message
    * @return void
    */
    public function error($message){
        $this->writeLog($message, 'ERROR');	
    }

    /**
    * Write to log file
    * @param string $message
    * @param string $severity
    * @return void
    */
    public function writeLog($message, $severity) {
        // open log file
        if (!is_resource($this->file)) {
            $this->openLog();
        }

        global $client_ip;
        $path = ($_SERVER["SERVER_NAME"] ?? getLocalhostIP()) . ($_SERVER["REQUEST_URI"] ?? '/CLI');

        //Grab time - based on timezone in php.ini
        $time = date($this->options['dateFormat']);
        // Write time, url, & message to end of file
        fwrite($this->file, "[$time] [$path] : [$severity] - $message [$client_ip]" . PHP_EOL);
    }
    /**
    * Open log file
    * @return void
    */
    private function openLog(){
        $openFile = $this->log_file;
        // 'a' option = place pointer at end of file
        $this->file = fopen($openFile, 'a') or exit("Can't open $openFile!");
    }

    /**
     * Class destructor
     */
    public function __destruct(){
        if ($this->file) {
            fclose($this->file);
        }
    }
}
