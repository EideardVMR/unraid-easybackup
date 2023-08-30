<?php

class Config {
    
    #region VM
    /** Aktiviert/Deaktiviert das sichern von VMs */
    public static $ENABLE_VM_BACKUP = true;
    /** Dateiendung für einen Snapshot */
    public static $SNAPSHOT_EXTENSION = 'snap';
    /** Pfad zum Backup Ordner für VMs */
    public static $VM_BACKUP_PATH = '/mnt/user/backup_internal/vms/';
    /** Namen der zu Ignorierenden VMs*/
    public static $VM_IGNORE_VMS = [];
    /** Pfade zu den zu ignorierenden VM Disks */
    public static $VM_IGNORE_DISKS = [];
    #endregion

    #region Container
    /** Aktiviert/Deaktiviert das sichern von Containern */
    public static $ENABLE_APPDATA_BACKUP = true;
    /** Pfad zum Backup Ordner für Docker Container */
    public static $APPDATA_BACKUP_PATH = '/mnt/user/backup_internal/appdata/';
    /** IDs von Containern die nicht gesichert werden sollen */
    public static $APPDATA_IGNORE_CONTAINER = [];
    /** Pfade die nicht gesichert werden sollen. */
    public static $APPDATA_IGNORE_BINDES = [];
    #endregion

    #region Komprimierung
    /** Komprimierung einschalten */
    public static $COMPRESS_BACKUP = false;
    /** Art der Komprimierung (Möglich: "zip", "tar.gz") */
    public static $COMPRESS_TYPE = 'tar.gz';
    #endregion

    #region Papierkorb
    /** Aktiviert/Deaktiviert den Papierkorb */
    public static $ENABLE_RECYCLE_BIN = true;
    /** Pfad zum Papierkorb */
    public static $RECYCLE_BIN_PATH = '/mnt/user/backup_internal/.Recycle.Bin/';
    #endregion

    #region BackupTime
    public static $MAX_CONSECUTIVE_BACKUPS = 7;
    public static $MAX_WEEK_BACKUPS = 5;
    public static $MAX_MONTH_BACKUPS = 12;
    public static $MAX_YEAR_BACKUPS = 10;
    public static $FIRSTBACKUPTIME = 0;
    #endregion

    #region Gotify message
    /** Gotify Server zum Senden von Ereignissen | mit http(s) und / am ende! | null = aus */
    public static $GOTIFY_ENABLED = false;
    /** Gotify Server zum Senden von Ereignissen | mit http(s) und / am ende! | null = aus */
    public static $GOTIFY_SERVER = '';
    /** Gotify Token zum Senden von Ereignissen | null = aus */
    public static $GOTIFY_TOKEN = '';
    /** Sendet eine Nachricht wenn der Job abgeschlossen wurde (Egal ob mit Fehlern oder nicht) */
    public static $GOTIFY_PUSH_ON_COMPLETE = true;
    /** Sendet eine Nachricht wenn der Job mit Fehlern beendet wurde. */
    public static $GOTIFY_PUSH_ON_ERROR = true;
    #endregion

    #region Logging
    public static $LOG_WRITE = true;
    public static $LOG_WRITE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'worker.log';
    public static $LOG_MAX_SIZE = 100 * 1024 * 1024;
    public static $LOG_PRINT_INFO = true;
    public static $LOG_PRINT_WARNINGS = true;
    public static $LOG_PRINT_ERRORS = true;
    #endregion

    #region Others
    public static $JOB_CACHE = __DIR__ . DIRECTORY_SEPARATOR . 'jobs.json';
    /**
     * 0 = Debug
     * 1 = Info
     * 2 = Warnings (empfohlen)
     * 3 = Errors
     */
    public static $LOG_LEVEL = 0;
    #endregion

    public static function saveConfig() {

        $reflection = new ReflectionClass('Config');
        $properties = $reflection->getProperties(ReflectionProperty::IS_STATIC);

        $jsonArray = array();

        foreach($properties as $property) {
            $x = $property->getName();
            $jsonArray[$property->getName()] = Config::$$x;

        }
        $config_json = json_encode($jsonArray);

        if(file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.json', $config_json)) {
            return true;
        }

        return false;

    }

    public static function Load() {

        $cfg_json = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.json');
        $cfg_array = json_decode($cfg_json, true);

        foreach($cfg_array as $key => $val) {
            self::$$key = $val;
        }

    }

}

    /*
if(!file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'config.json')) {

    define('ENABLE_VM_BACKUP', true);
    define('SNAPSHOT_EXTENSION', 'snap');
    define('VM_BACKUP_PATH', '/mnt/user/backup_internal/vms/');

    define('ENABLE_APPDATA_BACKUP', true);
    define('APPDATA_BACKUP_PATH', '/mnt/user/backup_internal/appdata/');
    
    define('ENABLE_RECYCLE_BIN', false);
    define('RECYCLE_BIN_PATH',   '/mnt/user/backup_internal/.Recycle.Bin/');
    
    define('GOTIFY_SERVER', 'https://gotify.michelhp.de/');
    define('GOTIFY_TOKEN', 'AZFq7vaMYBJEOeN');
    define('GOTIFY_PUSH_ON_COMPLETE', true);
    define('GOTIFY_PUSH_ON_ERROR', true);

    define('LOG_PRINT_INFO', true);
    define('LOG_PRINT_WARNINGS', true);
    define('LOG_PRINT_ERRORS', true);

} else {

    $config = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.json');
    $config = json_decode($config, true);

    foreach($config as $key => $val) {
        define($key, $val);
    }

}
*/

?>