<?php
require_once '/usr/local/emhttp/plugins/smbackup/includes/loader.php';

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
} else if($_POST['action'] == 'vm_settings'){

    Config::$ENABLE_VM_BACKUP = $_POST['vm_enable'] == 'true';
    Config::$VM_BACKUP_PATH = $_POST['backup_location'];
    
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
    
    exec('php /usr/local/emhttp/plugins/smbackup/job.php backup vm "' . $_POST['vm'] . '" > /dev/null &');
    
    /*
    $kvm = new KVM();
    $vms = $kvm->getVMs();
    foreach($vms as $vm) {
        if($vm->name == $_POST['vm']) {
            $vm->createBackup();
            echo "dsOK";
            exit;
        }
    }
    */

    echo "OK";
    exit;
}


else {
    echo 'Not implemented';
    exit;
}

?>
