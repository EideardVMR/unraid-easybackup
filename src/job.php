<?php

require_once '/usr/local/emhttp/plugins/smbackup/includes/loader.php';

if(($argv[1] ?? '') == 'backup') {
    
    if(($argv[2] ?? '') == 'all') {

        sendNotification(LANG_NOTIFY_FULLBACKUP_START, 'normal');

        $backupstate = true;

        $kvm = new KVM();
        $vms = $kvm->getVMs();
        foreach($vms as $vm) {

            if(!$vm->createBackup()) {
                $backupstate = false;
            }

        }
        
        $docker = new Docker();
        $containers = $docker->getContainers();
        foreach($containers as $container) {
            
            if(!$container->createBackup()){
                $backupstate = false;
            }

        }

        if($backupstate) {
            sendNotification(LANG_NOTIFY_FULLBACKUP_END, 'normal');
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