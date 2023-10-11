<?php
require_once '/usr/local/emhttp/plugins/easybackup/includes/loader.php';

if($_POST['action'] == 'create_snap') {

    $kvm = new KVM();
    $vms = $kvm->getVMs();

    foreach($vms as $vm) {
        if($vm->name != $_POST['vm']) { continue; } 

        if($vm->createSnapshot()) {
            echo 'OK';
            sendNotification(LANG_NOTIFY_SNAPSHOT_CREATED);
            exit;
        } else {
            echo $vm->error;
            sendNotification(LANG_NOTIFY_SNAPSHOT_CREATE_FAILED . '<br>' . $vm->error, 'warning');
            exit;
        }
    }

} else if($_POST['action'] == 'commit_snap') {

    $kvm = new KVM();
    $vms = $kvm->getVMs();

    foreach($vms as $vm) {
        if($vm->name != $_POST['vm']) { continue; } 

        if($vm->commitSnapshot($_POST['to'])) {
            echo 'OK';
            sendNotification(LANG_NOTIFY_SNAPSHOT_COMMITED);
            exit;
        } else {
            echo $vm->error;
            sendNotification(LANG_NOTIFY_SNAPSHOT_COMMIT_FAILED . '<br>' . $vm->error, 'warning');
            exit;
        }
    }

} else if($_POST['action'] == 'revert_snap') {

    $kvm = new KVM();
    $vms = $kvm->getVMs();

    foreach($vms as $vm) {
        if($vm->name != $_POST['vm']) { continue; } 

        if($vm->revertSnapshot($_POST['to'])) {
            echo 'OK';
            sendNotification(LANG_NOTIFY_SNAPSHOT_REVERTED);
            exit;
        } else {
            echo $vm->error;
            sendNotification(LANG_NOTIFY_SNAPSHOT_REVERT_FAILED . '<br>' . $vm->error, 'warning');
            exit;
        }
    }

} else if($_POST['action'] == 'startvm') {

    $kvm = new KVM();
    $vms = $kvm->getVMs();

    foreach($vms as $vm) {
        if($vm->name != $_POST['vm']) { continue; } 

        if($vm->startVM()) {
            echo 'OK';
            exit;
        } else {
            echo $vm->error;
            sendNotification(LANG_NOTIFY_VM_START_FAILED . '<br>' . $vm->error, 'warning');
            exit;
        }
    }

} else if($_POST['action'] == 'stopvm') {

    $kvm = new KVM();
    $vms = $kvm->getVMs();

    foreach($vms as $vm) {
        if($vm->name != $_POST['vm']) { continue; } 

        if($vm->shutdownVM()) {
            echo 'OK';
            exit;
        } else {
            echo $vm->error;
            sendNotification(LANG_NOTIFY_VM_STOP_FAILED . '<br>' . $vm->error, 'warning');
            exit;
        }
    }

} else if($_POST['action'] == 'ignore_vms'){
    if(isset($_POST['disable_vm'])) {
        Config::$VM_IGNORE_VMS = $_POST['disable_vm'];
    } else {
        Config::$VM_IGNORE_VMS = [];
    }
    
    if(isset($_POST['disable_vm_disk'])) {
        Config::$VM_IGNORE_DISKS = $_POST['disable_vm_disk'];
    } else {
        Config::$VM_IGNORE_DISKS = [];
    }
    
    if(Config::saveConfig() === true) {
        echo "OK";
        exit;
    } else {
        echo "Failed";
        exit;
    }
} else if($_POST['action'] == 'ignore_container'){
    if(isset($_POST['disable_container'])) {
        Config::$APPDATA_IGNORE_CONTAINER = $_POST['disable_container'];
    } else {
        Config::$APPDATA_IGNORE_CONTAINER = [];
    }
    
    if(isset($_POST['disable_bind'])) {
        Config::$APPDATA_IGNORE_BINDES = $_POST['disable_bind'];
    } else {
        Config::$APPDATA_IGNORE_BINDES = [];
    }
    
    if(Config::saveConfig() === true) {
        echo "OK";
        exit;
    } else {
        echo "Failed";
        exit;
    }
} else if($_POST['action'] == 'settings'){

    Config::$ENABLE_VM_BACKUP = $_POST['ENABLE_VM_BACKUP'] == 'true';
    Config::$VM_BACKUP_PATH = $_POST['VM_BACKUP_PATH'];
    Config::$SNAPSHOT_EXTENSION = $_POST['SNAPSHOT_EXTENSION'];

    Config::$ENABLE_APPDATA_BACKUP = $_POST['ENABLE_APPDATA_BACKUP'] == 'true';
    Config::$APPDATA_BACKUP_PATH = $_POST['APPDATA_BACKUP_PATH'];

    Config::$COMPRESS_BACKUP = $_POST['COMPRESS_BACKUP'] == 'true';
    Config::$COMPRESS_TYPE = $_POST['COMPRESS_TYPE'];

    Config::$ENABLE_RECYCLE_BIN = $_POST['ENABLE_RECYCLE_BIN'] == 'true';
    Config::$RECYCLE_BIN_PATH = $_POST['RECYCLE_BIN_PATH'];

    Config::$MAX_CONSECUTIVE_BACKUPS = $_POST['MAX_CONSECUTIVE_BACKUPS'];
    Config::$MAX_WEEK_BACKUPS = $_POST['MAX_WEEK_BACKUPS'];
    Config::$MAX_MONTH_BACKUPS = $_POST['MAX_MONTH_BACKUPS'];
    Config::$MAX_YEAR_BACKUPS = $_POST['MAX_YEAR_BACKUPS'];

    Config::$GOTIFY_ENABLED = $_POST['GOTIFY_ENABLED'] == 'true';
    Config::$GOTIFY_SERVER = $_POST['GOTIFY_SERVER'];
    Config::$GOTIFY_TOKEN = $_POST['GOTIFY_TOKEN'];
    Config::$GOTIFY_PUSH_ON_COMPLETE = $_POST['GOTIFY_PUSH_ON_COMPLETE'] == 'true';
    Config::$GOTIFY_PUSH_ON_ERROR = $_POST['GOTIFY_PUSH_ON_ERROR'] == 'true';
    Config::$GOTIFY_COMPLETE_MESSAGE = $_POST['GOTIFY_COMPLETE_MESSAGE'];

    Config::$LOG_LEVEL = $_POST['LOG_LEVEL'];
    
    if(Config::saveConfig() === true) {
        echo "OK";
        exit;
    } else {
        echo "Failed";
        exit;
    }
} else if($_POST['action'] == 'delete'){
    
    if(is_dir($_POST['file'])) {

        echo "Failed";
        exit;

    } else if(is_file($_POST['file'])) {

        if(DeleteFile($_POST['file'])) {
            
            echo "OK";
            if(Config::$ENABLE_RECYCLE_BIN) {
                sendNotification(sprintf(LANG_NOTIFY_FILE_RECYCLE_SUCCESS, $_POST['file']));
            } else {
                sendNotification(sprintf(LANG_NOTIFY_FILE_DELETE_SUCCESS, $_POST['file']));
            }
            exit;

        } else {
            
            echo "Failed";
            if(Config::$ENABLE_RECYCLE_BIN) {
                sendNotification(sprintf(LANG_NOTIFY_FILE_RECYCLE_FAILED, $_POST['file']), 'warning');
            } else {
                sendNotification(sprintf(LANG_NOTIFY_FILE_DELETE_FAILED, $_POST['file']), 'warning');
            }
            exit;

        }

    }

    echo "Failed";
    exit;
} else if($_POST['action'] == 'backupnow_vm'){
    
    exec('php /usr/local/emhttp/plugins/easybackup/job.php backup vm "' . $_POST['vm'] . '" > /dev/null &');

    echo "OK";
    exit;
} else if($_POST['action'] == 'backupnow_container'){
    
    exec('php /usr/local/emhttp/plugins/easybackup/job.php backup container "' . $_POST['container'] . '" > /dev/null &');

    echo "OK";
    exit;
} else if($_POST['action'] == 'clear_log'){
    
    if(file_exists('/boot/config/plugins/easybackup/easybackup.log')) {
        unlink('/boot/config/plugins/easybackup/easybackup.log');
    }

    echo "OK";
    exit;
}


else {
    echo 'Not implemented';
    exit;
}

?>
