<?php
// Allgemeine Informationen
define('NAME', 'Unraid All Backup');
define('VERSION', '0.1.0');

// Sicherheitshinweise
define('LANG_GUI_SECURITYNOTE_SNAPSHOT', '
    <p style="font-weight: bold;">Important security notes:</p>
        <ul>
            <li>Do not use plugins which create snapshots of VMs by themselves!</li>
            <li>NEVER create snapshots via the terminal!</li>
            <li>Before you edit a VM, commit all snapshots. 
                Otherwise Unraid deletes the snapshot configuration and your VM cannot start anymore!
            </li>
            <li>
                Not all changes are immediately visible execution. Wait a while and reload the interface. 
                This mostly concerns: start, stop and revert
            </li>
        </ul>
    </p>
');

define('LANG_GUI_SECURITYNOTE_VMBACKUPDISABLED','VM BACKUP IS DISABLED');

// Überschriften
define('LANG_GUI_HEADER_BACKUPS', 'Backups');
define('LANG_GUI_HEADER_SETTINGS', 'Settings');
define('LANG_GUI_HEADER_VMS', 'VMs');
define('LANG_GUI_HEADER_CONTAINER', 'Container');
define('LANG_GUI_HEADER_COMPRESSOION', 'Compression');
define('LANG_GUI_HEADER_GOTIFY', 'Gotify');
define('LANG_GUI_HEADER_RECYCLE', 'Recycle Bin');
define('LANG_GUI_HEADER_LOGS', 'Logs');

// Tabellen Spaltennamen
define('LANG_GUI_TABLE_COL_NAME', 'Name');
define('LANG_GUI_TABLE_COL_DISKS', 'Disk\'s');
define('LANG_GUI_TABLE_COL_ACTIONS', 'Actions');
define('LANG_GUI_TABLE_COL_DISABLE', 'Disable');
define('LANG_GUI_TABLE_COL_LASTBACKUP', 'Last backup');
define('LANG_GUI_TABLE_COL_BACKUPCOUNT', 'Backups stored');
define('LANG_GUI_TABLE_COL_BACKUPSIZE', 'Size');

// Buttonbezeichnungen
define('LANG_GUI_BTN_CREATE_SNAP', 'Create Snapshot');
define('LANG_GUI_BTN_REVERT_SNAP', 'Revert');
define('LANG_GUI_BTN_COMMIT_SNAP', 'Commit to %s');
define('LANG_GUI_BTN_SAVE', 'Save');
define('LANG_GUI_BTN_CANCLE', 'Cancle');
define('LANG_GUI_BTN_START', 'Start');
define('LANG_GUI_BTN_STOP', 'Stop');
define('LANG_GUI_BTN_DELETE', 'Delete');
define('LANG_GUI_BTN_RESTORE', 'Restore');
define('LANG_GUI_BTN_BACKUPNOW', 'Backup now');

// Notizen
define('LANG_GUI_NOTE_ARRAY_OFFLINE', 'Array must be **started** to view Virtual Machines');
define('LANG_GUI_NOTE_VMSERVICE_FAILED', 'Libvirt Service failed to start');
define('LANG_GUI_NOTE_VMSERVICE_STOPPED', 'VM Manager must be **started**');

// Benachrichtigungen
define('LANG_NOTIFY_SNAPSHOT_CREATED', 'Snapshot created');
define('LANG_NOTIFY_SNAPSHOT_CREATE_FAILED', 'Snapshot can not create');
define('LANG_NOTIFY_SNAPSHOT_COMMITED', 'Snapshot Commited');
define('LANG_NOTIFY_SNAPSHOT_COMMIT_FAILED', 'Snapshot can not commit');
define('LANG_NOTIFY_SNAPSHOT_REVERTED', 'Snapshot reverted');
define('LANG_NOTIFY_SNAPSHOT_REVERT_FAILED', 'Snapshot can not revert');
define('LANG_NOTIFY_VM_START_FAILED', 'Start VM Failed');
define('LANG_NOTIFY_VM_STOP_FAILED', 'Stop VM Failed');
define('LANG_NOTIFY_FILE_DELETE_SUCCESS','File is deleted<br>%s');
define('LANG_NOTIFY_FILE_DELETE_FAILED','Delete file failed<br>%s');
define('LANG_NOTIFY_FILE_RECYCLE_SUCCESS','File is recycled<br>%s');
define('LANG_NOTIFY_FILE_RECYCLE_FAILED','Recycle file failed<br>%s');
define('LANG_NOTIFY_START_BACKUP_VM','Backup VM %s is started.');
define('LANG_NOTIFY_END_BACKUP_VM','Backup VM %s is completed.');
define('LANG_NOTIFY_START_BACKUP_CONTAINER','Backup container %s is started.');
define('LANG_NOTIFY_END_BACKUP_CONTAINER','Backup container %s is completed.<br>%s');
define('LANG_NOTIFY_FAILED_BACKUP_VM','Backup VM %s was failed<br>%s.');
define('LANG_NOTIFY_FAILED_BACKUP_CONTAINER','Backup container %s was failed<br>%s.');
define('LANG_NOTIFY_FULLBACKUP_START','Backup started');
define('LANG_NOTIFY_FULLBACKUP_END','Backup completed.<br>%s');
define('LANG_NOTIFY_FULLBACKUP_FAILED','Backup failed');
define('LANG_NOTIFY_INVALID_VM_STATE','State of VM is not supported');
define('LANG_NOTIFY_GOTIFY_ERRORS','Backup completed with errors.');

// Nachrichten
define('LAMG_MSG_CONTAINER_TIMEOUT_FOR_START', 'Start container timeout');
define('LAMG_MSG_CONTAINER_TIMEOUT_FOR_STOP', 'Stop container timeout');

// Tooltips
define('LANG_GUI_TOOLTIP_SHUTDOWN_BEFORE_REVERT', 'Shutdown VM before revert snapshot');
define('LANG_GUI_TOOLTIP_START_BEFORE_COMMIT', 'Start VM before commit to snapshot');
define('LANG_GUI_TOOLTIP_START_BEFORE_SNAPSHOT', 'Start VM before create a snapshot');
define('LANG_GUI_TOOLTIP_DELETE_SHURE', 'Please Confirm to delete.');

// Sonstiges
define('LANG_GUI_TIMEFORMAT', 'H:i:s');
define('LANG_GUI_DATEFORMAT', 'Y-m-d');
define('LANG_GUI_DATETIMEFORMAT', 'Y-m-d H:i:s');
define('LANG_GUI_ENABLED', 'Enabled');
define('LANG_GUI_DISABLED', 'Disabled');
define('LANG_GUI_SNAPSHOT', 'Snapshot');
define('LANG_GUI_ORIGINAL', 'Original');
define('LANG_GUI_ORIGINAL_SIZE', 'Original Size');
define('LANG_GUI_COMPRESSED_SIZE', 'Compressed Size');
define('LANG_GUI_FILES', 'Files');
define('LANG_GUI_IN_TIME', 'in %s');
define('LANG_GUI_TIME', 'Time');

define('LANG_GUI_LOG_LEVEL0', '0 - Debug');
define('LANG_GUI_LOG_LEVEL1', '1 - Info');
define('LANG_GUI_LOG_LEVEL2', '2 - Warnings (recommended)');
define('LANG_GUI_LOG_LEVEL3', '3 - Errors');

define('LANG_GUI_BACKUPTIMES', [
    'day' => 'Daily',
    'week' => 'Weekly',
    'month' => 'Monthly',
    'year' => 'Yearly'
]);
define('LANG_GUI_VM_STATES', [
    'STATE_RUNNING' => 'running',
    'STATE_STOPPED' => 'shut off',
    'STATE_SUSPENDED' => 'idle',
    'STATE_SHUTDOWN' => 'in shutdown',
    'STATE_CRASHED' => 'crashed',
    'STATE_PMSUSPENDED' => 'pmsuspended',
    'STATE_UNKNOWN' => 'unknown'
]);

// Info zu Kompression
/*
    Geschwindkeiten: 
        5GB
            nativ: 1min - 5GB
            gz:    4min - 1,4GB
            zip:   10min - 1,4GB
    
    Nativ ist sehr schnell braucht aber viel Speicher
    tar.gz ist schneller als ZIP, einzelne Dateien lassen sich aber nicht aus dem Archiv entfernen. Berechtigungen usw. werden übernommen.
    .zip ist langsamer als tar.gz, einzelne Dateien lassen sich aus dem Archiv extrahieren. Berechtigungen usw. werden nicht übernommen.

    Nur Nativ und zip wird für automatisches restore unterstützt. 

*/

//-------------------------------
// Alte
define('LANG_FAIL_CREATE_DIRECTORY', 'Could not create directory "%s"');
define('LANG_FAIL_FILE_MOVE', 'Could not move: "%s" to "%s"');
define('LANG_FILE_EXISTS', 'File already exists: "%s"');
define('LANG_FAIL_FILE_DELETE', 'Could not remove file: "%s"');
define('LANG_FAIL_LOAD_XML', 'Could not load XML: %s');
define('LANG_FAIL_LOAD_DISKS', 'Could not load Disks: %s');
define('LANG_FAIL_PARSE_VM_NAME', 'Could not parse VM-String: %s');
define('LANG_FAIL_PARSE_DISK_LINE', 'Could not parse Diskline: %s');
define('LANG_FAIL_SNAPSHOT_ALREADY_EXISTS', 'Could not create Snapshot from %s --> %s');
define('LANG_OK_SNAPSHOT_CREATED', 'Snapshot for %s created.');
define('LANG_FAIL_SNAPSHOT_CREATE', 'Snapshot for %s could not create.');
define('LANG_OK_SNAPSHOT_COMITED', 'Snapshot for %s commited.');
define('LANG_FAIL_SNAPSHOT_COMIT', 'Snapshot for %s could not commit.');
/*
define('LANG_FILE_NOT_FOUND', 'File not found: "%s"');
define('LANG_DIRECTORY_NOT_FOUND', 'Could not find directory "%s"');
define('LANG_DIRECTORY_FOUND', 'Directory found "%s"');
define('LANG_FAIL_FILE_DATE', 'File date could not be determined: "%s"');
define('LANG_UNKNOWN_FILE_TYPE', 'Unknown Filetype.');
define('LANG_FAIL_COPY_FILE', 'Could not copy file: "%s"');
define('LANG_GOTIFY_TITLE', 'Unraid Backup');
define('LANG_GOTIFY_OK', 'Backup completed');
define('LANG_GOTIFY_ERR', 'Backup has %d errors');

define('LANG_ACTIVE', 'Active');
define('LANG_INACTIVE', 'Inactive');
define('LANG_ONLINE', 'Online');
define('LANG_OFFLINE', 'Offline');

#Config
define('LANG_CFG_LEGEND', 'Legend');
define('LANG_CFG_SYSTEM', 'System');
define('LANG_CFG_BACKUPSETTINGS', 'Backup');
define('LANG_CFG_LOGGING', 'LOGGING');
define('LANG_CFG_NOTIFICATION', 'Notification');
define('LANG_CFG_RECYCLEBIN', 'Recycle Bin');
define('LANG_TESTINGMODE', 'Testmode');
define('LANG_APPDATA_SOURCE_PATH', 'Appdata Source-Path');
define('LANG_APPDATA_DESTINATION_PATH', 'Appdata Target-Path');
define('LANG_VMS_SOURCE_PATH',  'VM Source-Path');
define('LANG_VMS_DESTINATION_PATH', 'VM Target-Path');
define('LANG_IGNORE_VMS_SOURCE', 'Excluded VM Directorys');
define('LANG_MAX_CONSECUTIVE_BACKUPS', 'Hold Last X Days as Backup');
define('LANG_MAX_WEEK_BACKUPS', 'Hold Last X Weeks as Backup');
define('LANG_MAX_MONTH_BACKUPS', 'Hold Last X Months as Backup'); 
define('LANG_MAX_YEAR_BACKUPS', 'Hold Last X Years as Backup');
define('LANG_ENABLE_RECYCLE_BIN', 'Recycle Bin');
define('LANG_RECYCLE_BIN_PATH',  ' -- Path');
define('LANG_PRINT_WARNINGS', 'CLI Print Warnings');
define('LANG_LANGUAGE', 'Language');
define('LANG_GOTIFY', 'Gotify Notification');
define('LANG_GOTIFY_SERVER', 'Gotify Server Address');
define('LANG_GOTIFY_TOKEN', 'Gotify Token');
define('LANG_GOTIFY_PUSH_ON_COMPLETE', 'Gotify message at complete backup');
define('LANG_GOTIFY_PUSH_ON_ERROR', 'Gotify message at errors');
define('LANG_ERRORLOG_PATH','ErrorLog-Path');
*/

?>