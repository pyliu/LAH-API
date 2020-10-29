<?php
/** 
* st-PHP-Logger - Simple PHP logging class. Log info, warning, error messages to log files.
* $time = date('Y-M-d');
* $log = new Logger('log/log-' . $time . '.txt');
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
    public function __construct($log_file = 'error.txt', $params = array()){
        $this->log_file = $log_file;
        $this->params = array_merge($this->options, $params);

        //Create log file if it doesn't exist.
        if(!file_exists($log_file)){               
            fopen($log_file, 'w') or exit("Can't create $log_file!");
        }

        //Check permissions of file.
        if(!is_writable($log_file)){   
            //throw exception if not writable
            throw new Exception("ERROR: Unable to write to file!", 1);
        }
    }

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

        // Enter the name of directory
        $pathdir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."log";
        // Enter the name to creating zipped directory
        $zipcreated = "log-${date}.zip";
        $zip_file = $pathdir.DIRECTORY_SEPARATOR.$zipcreated;
        $log_file = "log-${date}.log";
        $log_path = $pathdir.DIRECTORY_SEPARATOR.$log_file;

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
        $time = date($this->params['dateFormat']);
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
