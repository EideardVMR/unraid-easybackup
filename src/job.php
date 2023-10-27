<?php

require_once '/usr/local/emhttp/plugins/easybackup/includes/loader.php';
    
Jobs::deleteAbortedJobs(true);

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

        if(Config::$ENABLE_VM_BACKUP) {
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
        }
        
        if(config::$ENABLE_APPDATA_BACKUP) {
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
        }

        if(Config::$ENABLE_FLASH_BACKUP) {
            if(!BackupFlash()){
                $backupstate = false;
            }
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

            if(Config::$GOTIFY_ENABLED && Config::$GOTIFY_PUSH_ON_COMPLETE) {
                GotifyPush(Config::$GOTIFY_COMPLETE_MESSAGE, 'Easy Backup');
            }

        } else {
            sendNotification(LANG_NOTIFY_FULLBACKUP_FAILED, 'alert');

            if(Config::$GOTIFY_ENABLED && Config::$GOTIFY_PUSH_ON_ERROR) {
                GotifyPush(LANG_NOTIFY_GOTIFY_ERRORS, 'Easy Backup');
            }
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

    if(($argv[2] ?? '') == 'flash') {
        BackupFlash();
    }
}

if(($argv[1] ?? '') == 'cleanup') {

    $timeranges = CreateTimeranges();

    if(($argv[2] ?? '') == 'all') {

        Log::LogDebug('Starting Cleanup');

        $kvm = new KVM();
        $vms = $kvm->getVMs();

        foreach($vms as $vm) {
            Log::LogInfo('Cleanup VM: ' . $vm->name);

            $backups = $vm->getStoredBackups();
            $trs = $timeranges;
            foreach($backups as $backup) {

                $found = false;
                foreach($trs as $key2 => $tr) {
                    if($backup['TimestampUnix'] >= $tr['start'] && $backup['TimestampUnix'] <= $tr['end']) {
                        unset($trs[$key2]);
                        Log::LogDebug('Store Backup: ', $backup['FullPath']);
                        $found = true;
                        break;
                    }

                }

                if(!$found) {
                    Log::LogInfo('Clean Backup: ' . $backup['FullPath']);
                    RemoveBackup($backup['FullPath']);
                }

            }

        }

        $docker = new Docker();
        $containers = $docker->getContainers();
        foreach($containers as $container) {
            Log::LogInfo('Cleanup Container: ' . $container->name);

            $backups = $container->getStoredBackups();
            $trs = $timeranges;
            foreach($backups as $backup) {

                $found = false;
                foreach($trs as $key2 => $tr) {
                    if($backup['TimestampUnix'] >= $tr['start'] && $backup['TimestampUnix'] <= $tr['end']) {
                        unset($trs[$key2]);
                        Log::LogDebug('Store Backup: ', $backup['FullPath']);
                        $found = true;
                        break;
                    }

                }

                if(!$found) {
                    Log::LogInfo('Clean Backup: ' . $backup['FullPath']);
                    RemoveBackup($backup['FullPath']);
                }

            }

        }

    }

}
?>