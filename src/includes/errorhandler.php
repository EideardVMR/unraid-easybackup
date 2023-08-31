<?php
/**
 * @author Sascha Michel
 * @copyright 2020
 * @version 1.0.0
 */

 class LogType {

    const LOG_ERROR = 1;
    const LOG_WARNING = 2;
    const LOG_INFO = 4;
    const LOG_ALL = 7;

}

/******************************************************************** 
 *****************************ERRORHANDLER***************************
 *******************************************************************/
 
#ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
function myErrorHandler($errno, $errstr = '', $errfile = '', $errline = 0) {   

    if (!(error_reporting() & $errno)) {
        // Dieser Fehlercode ist nicht in error_reporting enthalten
        return true;
    }
    

    $time = date('Y-m-d H:i:s');
    $exit = false;
    
    $exit_warning = false;
    $exit_error = false;
    $exit_notice = false;
    
    $file_out = "";
    $disp_out = "";
    $error_bgcolor = "red";
    $error_color = '#fff';
    
    $error_nr = array(
        1 => 'E_ERROR',
        2 => 'E_WARNING',
        4 => 'E_PARSE',
        8 => 'E_NOTICE',
        16 => 'E_CORE_ERROR',
        32 => 'E_CORE_WARNING',
        64 => 'E_COMPILE_ERROR',
        128 => 'E_COMPILE_WARNING',
        256 => 'E_USER_ERROR',
        512 => 'E_USER_WARNING',
        1024 => 'E_USER_NOTICE',
        2048 => 'E_STRICT',
        4096 => 'E_RECOVERABLE_ERROR',
        8192 => 'E_DEPRECATED',
        16384 => 'E_USER_DEPRECATED'
    );
    
    switch ($errno) {
        case E_WARNING:
            $file_out .= "$time WARN $errstr".PHP_EOL;
            $error_bgcolor = "orange";
            $error_color = '#fff';
            $exit = $exit_warning;
            $logtype = LogType::LOG_WARNING;
            break;
        case E_NOTICE:
            $file_out .= "$time INFO $errstr".PHP_EOL;
            $error_bgcolor = "blue";
            $error_color = '#fff';
            $exit = $exit_notice;
            $logtype = LogType::LOG_INFO;
            break;
            
        case E_RECOVERABLE_ERROR:
            $file_out .= "$time ERR  $errstr".PHP_EOL;
            $exit = $exit_error;
            $logtype = LogType::LOG_ERROR;
            break;

        # Veraltete Methoden Fehler
        case E_DEPRECATED:
            $file_out .= "$time WARN $errstr".PHP_EOL;
            $logtype = LogType::LOG_WARNING;
            break;
            
        # Benutzer Fehler
        case E_USER_ERROR:
            $file_out .= "$time ERR  $errstr".PHP_EOL;
            $exit = $exit_error;
            $logtype = LogType::LOG_ERROR;
            break;
        case E_USER_WARNING:
            $file_out .= "$time WARN $errstr".PHP_EOL;
            $error_color = 'orange';
            $error_color = '#fff';
            $exit = $exit_warning;
            $logtype = LogType::LOG_WARNING;
            break;
        case E_USER_NOTICE:
            $file_out .= "$time INFO $errstr".PHP_EOL;
            $error_bgcolor = "blue";
            $error_color = '#fff';
            $exit = $exit_notice;
            $logtype = LogType::LOG_INFO;
            break;
        case E_USER_DEPRECATED:
            $file_out .= "$time WARN $errstr".PHP_EOL;
            $logtype = LogType::LOG_WARNING;
            break;
    
        default:
            $file_out = "$time ERR  Nr. $errno $errstr".PHP_EOL;
            $exit = true;
            $logtype = LogType::LOG_ERROR;
            break;
    }
    
    if($logtype == LogType::LOG_ERROR) {
        Log::LogError($errstr, $errline);
        Log::LogError('Trace:');
    } else if($logtype == LogType::LOG_WARNING) {
        Log::LogWarning($errstr, $errline);
        Log::LogWarning('Trace:');
    } else if($logtype == LogType::LOG_INFO) {
        Log::LogInfo($errstr, $errline);
        Log::LogInfo('Trace:');
    }
    
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT & DEBUG_BACKTRACE_IGNORE_ARGS);

    #print_debug($backtrace);
    foreach($backtrace as $key => $trace){
        if($trace['function'] != 'myErrorHandler') {
    
            if($logtype == LogType::LOG_ERROR) {
                Log::LogError($trace['file'] . ':' . $trace['line'], $errline);
            } else if($logtype == LogType::LOG_WARNING) {
                Log::LogWarning($trace['file'] . ':' . $trace['line'], $errline);
            } else if($logtype == LogType::LOG_INFO) {
                Log::LogInfo($trace['file'] . ':' . $trace['line'], $errline);
            }

        }
    }

    if($exit)
        exit;

    return true;
}

register_shutdown_function( "fatal_handler" );
function fatal_handler() {

    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if( $error !== NULL) {
        Log::LogError($error['message'], $errline, 'global');
    }
}

$__old_error_handler = set_error_handler("myErrorHandler");

?>