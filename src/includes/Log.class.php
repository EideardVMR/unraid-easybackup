<?php
class Log {

    static $logging = true;

    static function LogError($msg) {
        self::Write(3, $msg);
    }

    static function LogWarning($msg) {
        self::Write(2, $msg);
    }

    static function LogInfo($msg) {
        self::Write(1, $msg);
    }

    static function LogDebug($msg) {
        self::Write(0, $msg);
    }

    static function Write($level, $msg){
        
        if(!self::$logging) { return; }
        
        $type = ' UUUUU ';

        if(Config::$LOG_LEVEL > $level) { return; }

        switch($level) {
            case 0: $type = ' DEBUG    '; break;
            case 1: $type = ' INFO     '; break;
            case 2: $type = ' WARNING  '; break;
            case 3: $type = ' ERROR    '; break;
        }

        $msg = date('Y-m-d H:i:s') . $type . $msg . "\r\n";

        if(!file_exists(Config::$LOG_WRITE_PATH)) {
            file_put_contents(Config::$LOG_WRITE_PATH, "--------------------------------\r\nEasyBackup\r\n--------------------------------\r\n");
        }

        if(!is_writable(Config::$LOG_WRITE_PATH)) {
            echo "Not writeable: " . Config::$LOG_WRITE_PATH;
            exit;
        }
        file_put_contents(Config::$LOG_WRITE_PATH, $msg, FILE_APPEND);

        $fs = filesize(Config::$LOG_WRITE_PATH);
        if($fs > Config::$LOG_MAX_SIZE) {
            cmdExec('tar cfz boot/config/plugins/easybackup/old_logs.tar.gz boot/config/plugins/easybackup/easybackup.log', $exec_out, $error);
        }
    }

}

?>