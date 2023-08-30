<?php

class LogType {

    const LOG_ERROR = 1;
    const LOG_WARNING = 2;
    const LOG_INFO = 4;
    const LOG_ALL = 7;

}

class Log {

    static function LogError($msg) {
        $msg = date('Y-m-d H:i:s') . ' ERROR ' . $msg . "\r\n";
        file_put_contents('/usr/local/emhttp/plugins/smbackup/includes/worker.log', $msg, FILE_APPEND);
    }

    static function LogInfo($msg) {
        $msg = date('Y-m-d H:i:s') . ' INFO  ' . $msg . "\r\n";
        file_put_contents('/usr/local/emhttp/plugins/smbackup/includes/worker.log', $msg, FILE_APPEND);
    }

    static function LogWarning($msg) {
        $msg = date('Y-m-d H:i:s') . ' WARN  ' . $msg . "\r\n";
        file_put_contents('/usr/local/emhttp/plugins/smbackup/includes/worker.log', $msg, FILE_APPEND);
    }

    static function LogDebug($msg) {
        $msg = date('Y-m-d H:i:s') . ' DEBUG ' . $msg . "\r\n";
        file_put_contents('/usr/local/emhttp/plugins/smbackup/includes/worker.log', $msg, FILE_APPEND);
    }

}

?>