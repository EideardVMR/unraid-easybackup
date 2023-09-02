<?php

require_once '/usr/local/emhttp/plugins/easybackup/includes/loader.php';

if(($argv[1] ?? '') == 'backup') {
    
    if(($argv[2] ?? '') == 'all') {

        sendNotification(LANG_NOTIFY_FULLBACKUP_START, 'normal');

        $backupstate = true;
        $backup_compressioninfo = [
            'Files' => 0,
            'OriginalSize' => 0,
            'CompressedSize' => 0,
            'Time' => 0
        ];

        $kvm = new KVM();
        $vms = $kvm->getVMs();
        foreach($vms as $vm) {

            if(!$vm->createBackup()) {
                $backupstate = false;
            }

            $backup_compressioninfo['Files'] += $vm->backup_compressioninfo['Files'];
            $backup_compressioninfo['OriginalSize'] += $vm->backup_compressioninfo['OriginalSize'];
            $backup_compressioninfo['CompressedSize'] += $vm->backup_compressioninfo['CompressedSize'];
            $backup_compressioninfo['Time'] += $vm->backup_compressioninfo['Time'];

        }
        
        $docker = new Docker();
        $containers = $docker->getContainers();
        foreach($containers as $container) {

            if(!$container->createBackup()){
                $backupstate = false;
            }
            
            $backup_compressioninfo['Files'] += $vm->backup_compressioninfo['Files'];
            $backup_compressioninfo['OriginalSize'] += $vm->backup_compressioninfo['OriginalSize'];
            $backup_compressioninfo['CompressedSize'] += $vm->backup_compressioninfo['CompressedSize'];
            $backup_compressioninfo['Time'] += $vm->backup_compressioninfo['Time'];

        }

        if($backupstate) {
            $tmp = '';
            $tmp .= LANG_GUI_FILES . ': ' . $backup_compressioninfo['Files'] . '<br>';
            $tmp .= LANG_GUI_ORIGINAL_SIZE . ': ' . convertSize($backup_compressioninfo['OriginalSize']) . '<br>';
            $tmp .= LANG_GUI_COMPRESSED_SIZE . ': ' . convertSize($backup_compressioninfo['CompressedSize']) . '<br>';
            $tz = date_default_timezone_get();
            date_default_timezone_set('UTC');
            $tmp .= LANG_GUI_TIME . ': ' . date('H:i:s', $backup_compressioninfo['Time']) . '<br>';
            date_default_timezone_set($tz);

            sendNotification(sprintf(LANG_NOTIFY_FULLBACKUP_END, $tmp), 'normal');
        } else {
            sendNotification(LANG_NOTIFY_FULLBACKUP_FAILED, 'alert');
        }

    }

    if(($argv[2] ?? '') == 'vm') {
        
        $kvm = new KVM();
        $vms = $kvm->getVMs();
        foreach($vms as $vm) {

            if($vm->name == ($argv[3] ?? '')) {

                $vm->createBackup(true);

            }

        }

    }
    
    if(($argv[2] ?? '') == 'container') {
        
        $docker = new Docker();
        $containers = $docker->getContainers();
        foreach($containers as $container) {

            if($container->name == ($argv[3] ?? '')) {

                $container->createBackup(true);

            }

        }

    }

}
?>