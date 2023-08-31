<?php

/*
Befehle:
    Aktuelle VMs auflisten
        virsh list
    XML Daten auslesen:
        virsh dumpxml "[Name der VM]"
    Aktuell genutzte Festplatten auslesen:
        virsh domblklist "[Name der VM]"
    Snapshot erzeugen:
        virsh snapshot-create-as --domain "[Name der VM]" --name "[Name des Snapshots]" --disk-only --quiesce --no-metadata
            Für eine spezielle vDisk:
                --diskspec hdc,file=[Pfad zur neuen Datei]
    Snapshots auflisten (sofern nicht --no-metadata verwendet wurde!)
        virsh snapshot-list --domain "[Name der VM]"
    Snapshot metadaten entfernen (sofern nicht --no-metadata verwendet wurde!)
        virsh snapshot-delete "[Name der VM]" --metadata "[Name des Snapshots]"
    Snapshot mit Original zusammenfügen:
        virsh blockcommit "[Name der VM]" hdc --active --verbose --pivot
    Snapshotdatei löschen
        rm [Dateiname]
*/

enum VMState : string {

    case  STATE_RUNNING = 'running';
    case  STATE_STOPPED = 'shut off';
    case  STATE_SUSPENDED = 'idle';
    case  STATE_PAUSED = 'paused';
    case  STATE_SHUTDOWN = 'in shutdown';
    case  STATE_CRASHED = 'crashed';
    case  STATE_PMSUSPENDED = 'pmsuspended';
    case  STATE_UNKNOWN = 'unknown';

}

class VM {
    
    public string $name = '';
    public VMState $state = VMState::STATE_UNKNOWN;
    public Array $disks = [];
    public bool $hasSnapshot = false;
    
    public int $id = -1;
    public string $uuid = '';
    private string|null $xml = null;
    public string|null $error = null;
    public int|null $lastSnapshotNumber = null;
        
    /**
     * getBackingStore
     * Liefert Pfade zu Imagedateien die einer Snapshot Image Datei bevorstehen aus dem XML. 
     * Die Reihenfolge ist die in der die Snapshots erzeugt wurden!
     * Die letzte Datei in der Reihe ist die initiale ImageDatei
     * @param SimpleXMLElement $backingStore
     * @return array Array mit den Pfaden zu den Dateien
     */
    private static function getBackingStore(SimpleXMLElement $backingStore) : array {
        $stores = [];
    
        if($backingStore->attributes() !== null && count($backingStore->attributes()) > 0) {
            $stores[] = $backingStore->source->attributes()['file'];
            $tmp = self::getBackingStore($backingStore->backingStore);
            $stores = array_merge($stores, $tmp);
        }
        return $stores;
    }
    
    /**
     * getXML
     * Liefert die XML Konfiguration der VM (cached!!!)
     * @param  bool $force_reload true = ignoriert den cache und lädt die XML neu
     * @return string|bool false bei einem Fehler ansonsten den XML String 
     */
    public function getXML(bool $force_reload = false) : string|bool {
        
        if($this->xml === null || $force_reload) {
            $cmd = 'virsh dumpxml "' . $this->name . '"';
            Log::LogDebug('VM: Load XML: ' . $cmd);
            exec($cmd, $xml);
            if($xml === "error: failed to get domain '{$this->name}'") {
                Log::LogError('VM: Loading XML Failed:');
                Log::LogError(print_r($xml, true));
                return false;
            }
            $this->xml = implode("\r\n",$xml);
        }

        return $this->xml;
    }
        
    /**
     * loadUsedDisks
     * Lädt die aktuellen vDisks (inkl. der Snapshots) und legt diese in $disks ab
     * @return array Array mit den vDisks
     */
    public function loadUsedDisks() : array {

        Log::LogDebug('VM: Load Disks');
        $this->disks = [];
        $xml = @new SimpleXMLElement($this->getXML(true));
        $disks = $xml->devices->disk;
        for($i = 0; $i < $disks->count(); $i++) {
            $path = $disks[$i]->source->attributes()['file'];
            $target = $disks[$i]->target->attributes()['dev'];

            $dsk = [
                'Target' => (string)$target,
                'Source' => (string)$path,
                'PreSource' => null
            ];
            
            if($disks[$i]->backingStore->attributes() !== null && count($disks[$i]->backingStore->attributes()) > 0) {
                $this->hasSnapshot = true;
                $dsk['PreSource'] = [];
                foreach(self::getBackingStore($disks[$i]->backingStore) as $store) {
                    $dsk['PreSource'][] = (string)$store;
                }
            }

            $this->disks[] = $dsk;
        }

        return $this->disks;
    }
        
    /**
     * loadInfo
     * Lädt alle Systeminformationen
     * @return void
     */
    public function loadInfo() {

        $cmd = 'virsh dominfo "' . $this->name . '"';
        Log::LogDebug('VM: Load VM Informations: ' . $cmd);
        exec($cmd, $output);
        Log::LogDebug(print_r($output, true));

        foreach($output as $key => $o) {
            $ex = explode(':', $o, 2);
            if(count($ex) == 2) {
                $output[$ex[0]] = trim($ex[1]);
                unset($output[$key]);
            }
        }

        $this->id = $output['Id'] == '-' ? -1 : (int)$output['Id'];
        $this->name = $output['Name'];
        $this->state = VMState::tryFrom(trim($output['State'])) ?? VMState::STATE_UNKNOWN;
        $this->uuid = $output['UUID'];

        $snapnumber = -1;

        foreach($this->disks as $disk) {

            $info = pathinfo($disk['Source']);
            preg_match('/'.Config::$SNAPSHOT_EXTENSION.'([0-9]*)/', $info['extension'], $output_array);
            
            if(count($output_array) == 2) {
                $snapnumber = $output_array[1];
            }

        }

        if($snapnumber == -1 || (!is_int($snapnumber) && !is_null($snapnumber))) {
            $this->lastSnapshotNumber = null;
            return;
        }
        
        $this->lastSnapshotNumber = $snapnumber;
        
    }

    /**
     * createSnapshot
     * Erzeugt einen Snapshot aller vDisks
     * @return bool true wenn es geklappt hat, false wenn nicht
     */
    public function createSnapshot() : bool {

        LOG::LogDebug('VM: Start to create snapshot of "' . $this->name . '"');
        if($this->state != VMState::STATE_RUNNING) {
            $this->error = 'VM must be active';
            LOG::LogInfo('VM: Create snapshot is blocked. VM is not running');
            return false;
        }

        $snapnumber = 0;
        $targetfile = null;
        $targetxml = null;

        // Original XML erstellen
        foreach($this->disks as $disk) {
            $pi = pathinfo($disk['Source']);
            if($disk['PreSource'] == null) {
                LOG::LogInfo('VM: Create file: ' . $pi['dirname'] . '/' . $pi['extension'] . '.xml');
                file_put_contents($pi['dirname'] . '/' . $pi['extension'] . '.xml', $this->getXML(true));
            }
        }

        // Prüfe ob alle vDisk in einem Verzeichnis liegen
        $lastdirname = null;
        foreach($this->disks as $disk) {

            $info = pathinfo($disk['Source']);
            if($lastdirname !== null) {
                if($lastdirname !== $info['dirname']) {
                    LOG::LogWarning("VM: Create snapshot is blocked. Disk are not in the same share.");
                    $this->error = "All image-files must be in the same share";
                    return false;
                }
            }

            $lastdirname = $info['dirname'];

        }

        // Prüfen ob Snapshot-Dateien bereits existieren
        foreach($this->disks as $disk) {

            $info = pathinfo($disk['Source']);
            preg_match('/'.Config::$SNAPSHOT_EXTENSION.'([0-9]*)/', $info['extension'], $output_array);
            
            if(count($output_array) == 2) {
                $snapnumber = $output_array[1]+1;
            }
            LOG::LogDebug("VM: New Snapshotextension: " . Config::$SNAPSHOT_EXTENSION . $snapnumber);

            $targetfile = $info['dirname'] . '/' . $info['filename'] . '.' . Config::$SNAPSHOT_EXTENSION . $snapnumber;
            
            if(file_exists($targetfile)) {
                $this->error = 'File "' . $targetfile . '" already exists<br>';
                LOG::LogWarning('VM: File "' . $targetfile . '" already exists');
                return false;
            }

            break;

        }

        // Pfad zur XML Datei 
        $targetxml = $lastdirname . '/' . Config::$SNAPSHOT_EXTENSION . $snapnumber . '.xml';
        
        // Snapshot erzeugen
        $cmd = 'virsh snapshot-create-as --domain "' . $this->name . '" --name "' . Config::$SNAPSHOT_EXTENSION . $snapnumber . '" --disk-only --quiesce --no-metadata';
        exec($cmd, $exec_out);
        LOG::LogDebug("VM: Create Snapshot: " . $cmd);
        if($exec_out[0] == 'Domain snapshot ' . Config::$SNAPSHOT_EXTENSION . $snapnumber . ' created') {
            
            LOG::LogInfo('VM: Snapshot "' . Config::$SNAPSHOT_EXTENSION . $snapnumber . '" created');
            $this->hasSnapshot = true;
            
            // Alle Disks neu laden
            $this->loadUsedDisks();
            
            // XML Datei der neuen Konfiguration ablegen
            LOG::LogDebug("VM: Create new xml File:" . $targetxml);
            if(!file_put_contents($targetxml, $this->getXML(true))){
                LOG::LogError('VM: Could not create "' . $targetfile . '".');
                $this->error = "Failed to create new XML";
                return false;
            }
            
            sleep(5);
            $this->lastSnapshotNumber = $snapnumber;
            return true;

        }

        Log::LogError('VM: Create Snapshot "' . Config::$SNAPSHOT_EXTENSION . $snapnumber . '" failed');
        Log::LogError(print_r($exec_out, true));
        $this->error = "Faild to create Snapshot: " . $exec_out[0];
        return false;

    }
    
    /**
     * commitSnapshot
     * Committed aktiven snapshot in einen vorherigen oder in das original (default)
     * @param  string $to dateierweiterung des snapshots auf den committed werden soll
     * @return bool true wenn es geklappt hat, false wenn nicht 
     */
    public function commitSnapshot(string $to = 'original') : bool {
        
        Log::LogDebug('VM: Start to commit snapshot of "' . $this->name . '" to ' . $to);

        $xmls = [];
        $commit_to = [];
        $targets = [];

        if($this->state != VMState::STATE_RUNNING) {
            $this->error = 'VM must be active';
            LOG::LogInfo('VM: Commit snapshot is blocked. VM is not running');
            return false;
        }

        // Bestimmen der Dateien die Commited und gelöscht werden müssen.
        foreach($this->disks as $disk) {

            $pi = pathinfo($disk['Source']);
            $xmls[] = $pi['dirname'] . '/' . $pi['extension']. '.xml';

            if($to == 'original') {
                $commit_to[] = $disk['PreSource'][count($disk['PreSource'])-1];
            }

            $targets[] = $disk['Target'];

            foreach($disk['PreSource'] as $presource) {
                $pi = pathinfo($presource);
                if($pi['extension'] == $to) {
                    $commit_to[] = $presource;
                    continue 2;
                } else {
                    $xmls[] = $pi['dirname'] . '/' . $pi['extension']. '.xml';
                }
            }

        }

        $errors = [];
        foreach($targets as $key => $target) {

            $command = [];
            $command[] = 'virsh blockcommit';
            $command[] = '"' . $this->name . '"';
            $command[] = $target;
            $command[] = '--base "' . $commit_to[$key] . '"';
            #$command[] = '--active';
            $command[] = '--pivot';
            $command[] = '--delete';

            Log::LogDebug('VM: Commit Snapshot: ' . join(' ', $command));
            exec(join(' ', $command), $output);
            if($output[1] != 'Successfully pivoted') {
                Log::LogWarning('VM: Commit failed');
                Log::LogWarning(print_r($output, true));
                $errors[] = 'Commit ' . $target . ' failed';
            }
        }

        if(count($errors) > 0) {
            $this->error = join('<br>', $errors);
            return false;
        }

        foreach($xmls as $xml) {
            if(file_exists($xml)) {
                Log::LogInfo('VM: Remove file: ' . $xml);
                unlink($xml);
            }
        }

        // Erstelle eine neue orginal XML
        if($to == 'original') {
            $this->loadUsedDisks();
            foreach($this->disks as $disk) {
                $pi = pathinfo($disk['Source']);
                if($disk['PreSource'] == null) {
                    Log::LogDebug('VM: Create new XML: ' . $pi['dirname'] . '/' . $pi['extension'] . '.xml');
                    file_put_contents($pi['dirname'] . '/' . $pi['extension'] . '.xml', $this->getXML(true));
                }
            }
        }

        sleep(5);
        return true;

    }
    
    /**
     * revertSnapshot
     * Setzt eine VM in einen älteren Zustand zurück.
     * @param  mixed $to Name des Snapshots (snap[X])
     * @return bool true wenn es geklappt hat, false wenn nicht
     */
    public function revertSnapshot(string $to = 'original') : bool {

        Log::LogDebug('VM: Start to revert snapshot of "' . $this->name . '" to ' . $to);

        $this->loadInfo();
        if($this->state != VMState::STATE_STOPPED) {
            $this->error = 'VM must be stopped';
            return false;
        }

        $xml_file = null;

        if($to == 'original') {
            $this->loadUsedDisks();
            foreach($this->disks as $disk) {
                $pi = pathinfo($disk['PreSource'][count($disk['PreSource'])-1]);
                $xml_file = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
            }
        } else {
            foreach($this->disks as $disk) {
                foreach($disk['PreSource'] as $presource) {
                    $pi = pathinfo($presource);
                    if($pi['extension'] == $to) {
                        $xml_file = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
                    }
                }
            }
        }

        if($xml_file === null || !file_exists($xml_file)) {
            Log::LogWarning('VM: XML not found. Revert aborted.');
            $this->error = 'No XML found';
            return false;
        }

        $remove_disks = [];
        $remove_xml = [];
        foreach($this->disks as $disk) {
            $pi = pathinfo($disk['Source']);
            $remove_disks[] = $disk['Source'];
            $remove_xml[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
            foreach($disk['PreSource'] as $key => $presource) {
                $pi = pathinfo($presource);
                if($pi['extension'] != $to && $key < count($disk['PreSource'])-1 ) {
                    $remove_disks[] = $presource;
                    $remove_xml[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
                } else {
                    break;
                }
            }
        }

        $remove_xml = array_unique($remove_xml);

        $cmd = 'virsh define "' . $xml_file . '"';
        Log::LogDebug('VM: Start to revert snapshot of "' . $this->name . '" to ' . $to);
        exec($cmd, $output);
        if($output[0] != 'Domain \'' . $this->name . '\' defined from ' . $xml_file . '') {
            Log::LogWarning('VM: Could not define new XML');
            Log::LogWarning(print_r($output, true));
            return false;
        }

        foreach($remove_disks as $rm) {
            Log::LogInfo('VM: Remove ' . $rm);
            unlink($rm);
        }

        foreach($remove_xml as $rm) {
            Log::LogInfo('VM: Remove ' . $rm);
            unlink($rm);
        }

        sleep(5);
        return true;

    }
    
    /**
     * startVM
     * Startet die VM
     * @return bool true wenn es geklappt hat, false wenn nicht
     */
    public function startVM() : bool{

        Log::LogDebug('VM: Start VM "' . $this->name . '"');
        if($this->state == VMState::STATE_RUNNING) { 
            Log::LogInfo('VM: Start VM "' . $this->name . '" aborted. VM is running.');
            return true;
        }
        if($this->state !== VMState::STATE_STOPPED) { 
            $this->error = "VM can not shutdown from this state";
            Log::LogInfo('VM: Start VM "' . $this->name . '" aborted. VM is in unsupported State.');
            return false;
        }

        $cmd = 'virsh start "' . $this->name . '"';
        Log::LogInfo('VM: Start VM: ' . $cmd);
        exec($cmd, $output);
        if($output[0] != "Domain '" . $this->name . "' started") {
            Log::LogWarning('VM: Start VM failed');
            Log::LogWarning(print_r($output, true));
            $this->error = $output[0];
            return false;
        }

        $this->loadInfo();
        sleep(2);
        return true;
    }
    
    /**
     * shutdownVM
     * Beendet die VM
     * @return bool true wenn es geklappt hat, false wenn nicht
     */
    public function shutdownVM() : bool{
        Log::LogDebug('VM: Shutdown VM "' . $this->name . '"');
        if($this->state == VMState::STATE_STOPPED) { 
            Log::LogInfo('VM: Shutdown VM "' . $this->name . '" aborted. VM is offline.');
            return true;
        }
        if($this->state !== VMState::STATE_RUNNING) { 
            $this->error = "VM can not shutdown from this state";
            Log::LogInfo('VM: Shutdown VM "' . $this->name . '" aborted. VM is in unsupported State.');
            return false;
        }

        $cmd = 'virsh shutdown "' . $this->name . '"';
        Log::LogInfo('VM: Shutdown VM: ' . $cmd);
        exec($cmd, $output);
        if($output[0] != "Domain '" . $this->name . "' is being shutdown") {
            Log::LogWarning('VM: Shutdown VM failed');
            Log::LogWarning(print_r($output, true));
            $this->error = $output[0];
            return false;
        }

        $this->loadInfo();
        sleep(5);
        return true;
    }
    
    /**
     * getStoredBackups
     * Gibt Informationen für die gespeicherten Backups zurück
     * @return array 
     */
    public function getStoredBackups() : array {

        $output = [];
        $files = scandir(Config::$VM_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR);
        foreach($files as $file) {
            if($file == '.' || $file == '..') { continue; }

            $pi = pathinfo(Config::$VM_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR . $file);

            preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})_([0-9]{2})\.([0-9]{2})(\.tar\.gz|\.zip)?$/', $file, $output_array);

            if(count($output_array) != 6 && count($output_array) != 7) {
                continue;
            }

            $backuptype = 'nativ';
            if(count($output_array) == 7) {
                if($output_array[6] == '.tar.gz') {
                    $backuptype = 'TAR in GZip';
                } else if($output_array[6] == '.zip') {
                    $backuptype = 'ZIP Archiv';
                } else {
                    $backuptype = 'unknown!';
                }
            }

            $timestamp = $output_array[1].'-'.$output_array[2].'-'.$output_array[3].' '.$output_array[4].':'.$output_array[5];

            $o = [
                'FullPath' => $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['basename'],
                'FileName' => $pi['basename'],
                'Timestamp' => $timestamp,
                'TimestampUnix' => strtotime($timestamp),
                'Size' => filesize(Config::$VM_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR . $file),
                'BackupType' => $backuptype
            ];

            $output[] = $o;

        }

        usort($output, function($a, $b) {
            return $b['TimestampUnix'] <=> $a['TimestampUnix'];
        });

        return $output;
    }

    public function createBackup(){

        Log::LogDebug('VM: Start Backup "' . $this->name . '"');
        // Ablehnen wenn VM nicht im definierten Status ist. 
        if($this->state != VMState::STATE_RUNNING && $this->state != VMState::STATE_STOPPED) {
            Log::LogInfo('VM: Backup can not start in unsupported state. Backup aborted');
            $this->error = "VM must be started or stopped to backup it.";
            return false;
        }
        
        // Lade Informationen (um die letzte Snapshotversion zu erhalten)
        $this->loadInfo();

        // Prüfen ob der Job bereits läuft.
        if(Jobs::check(JobCategory::VM, 'backup', $this->uuid)) {
            Log::LogInfo('VM: Backup is running. Backup aborted.');
            $this->error = 'Job is running';
            return true;
        }

        // Backuppfad bestimmen
        $target_path = Config::$VM_BACKUP_PATH . $this->name . '/';
        Log::LogDebug('VM: Backuppath: ' . $target_path);
        if(!CheckFilesExists($target_path)) {
            Log::LogInfo('VM: Create Backuppath: ' . $target_path);
            mkdir($target_path, 0777, true);
        }

        // Backup erstellen mit Snapshot
        if($this->state == VMState::STATE_RUNNING) {
            
            Log::LogDebug('VM: Backup of running maschine');

            // Informationen commit des Snapshots sammeln
            $snapshot_commit = 'original';
            if($this->lastSnapshotNumber !== null) {
                $snapshot_commit = Config::$SNAPSHOT_EXTENSION . $this->lastSnapshotNumber;
            }
            Log::LogDebug('VM: After Backup commit to ' . $snapshot_commit);

            // Nachricht senden
            sendNotification(sprintf(LANG_NOTIFY_START_BACKUP_VM, $this->name));

            // Job hinzufügen
            Jobs::add(JobCategory::VM, 'backup', $this->uuid);

            // Snapshot erstellen
            $this->createSnapshot();
            sleep(30);
            $this->loadUsedDisks();

            // Dateien bestimmen, die kopiert werden müssen.
            $copy_files = [];
            foreach($this->disks as $disk) {
                foreach($disk['PreSource'] as $pre) {
                    $copy_files[] = $pre;
                    $pi = pathinfo($pre);
                    $copy_files[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
                }
            }

            // Backup mit kompression
            if(Config::$COMPRESS_BACKUP) {

                // Backup ZIP Kompression
                if(Config::$COMPRESS_TYPE == 'zip') {

                    $target_filename = $target_path . date('Y-m-d_H.i') . '.zip';
                    Log::LogInfo('VM: Compress Backup to Zip: ' . $target_filename);

                    $zip = new ZipArchive();
                    if($zip->open($target_filename, ZipArchive::CREATE) !== true) {

                        sleep(30);
                        $this->commitSnapshot($snapshot_commit);
                        sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'invalid compression type'), 'warning');
                        Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                        return false;

                    }

                    $fileinfos = [];
                    foreach($copy_files as $file) {

                        Log::LogDebug('VM: Add File "'.$file.'" to Zip!');

                        $pi = pathinfo($file);
                        if(!$zip->addFile($file, $pi['basename'])) {
                            Log::LogError('VM: File "'.$file.'" could not add to Zip!');

                            sleep(30);
                            $this->commitSnapshot($snapshot_commit);
                            sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'file can not compress'), 'warning');
                            Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                            return false;

                        }

                        $fileinfos[] = [
                            'File' => $file,
                            'InArchive' => $pi['basename'],
                            'Permissions' => substr(sprintf('%o', fileperms($file)), -4),
                            'User' => posix_getpwuid(fileowner($file))['name'],
                            'Group' => posix_getgrgid(filegroup($file))['name']
                        ];

                    }
                    
                    $zip->addFromString('fileinfo.json', json_encode($fileinfos));

                    if(!$zip->close()) {
                        Log::LogError('VM: could not end Zipfile!');

                        sleep(30);
                        $this->commitSnapshot($snapshot_commit);
                        sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'can not write ZipFile'), 'warning');
                        Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                        return false;

                    }


                
                // Backup GZ Komprimiert
                } else if(Config::$COMPRESS_TYPE == 'tar.gz') {

                    $target_filename = $target_path . date('Y-m-d_H.i') . '.tar.gz';
                    Log::LogInfo('VM: Compress Backup to gz: ' . $target_filename);
                    Log::LogWarning('VM: Actualy... for this compression is no restore supported!');

                    $cmd = 'tar -czf "' . $target_filename . '" ';

                    $fileinfos = [];
                    foreach($copy_files as $file) {

                        $pi = pathinfo($file);
                        $cmd .= '"' . $file . '" ';

                        $fileinfos[] = [
                            'File' => $file,
                            'InArchive' => $pi['basename'],
                            'Permissions' => substr(sprintf('%o', fileperms($file)), -4),
                            'User' => posix_getpwuid(fileowner($file))['name'],
                            'Group' => posix_getgrgid(filegroup($file))['name']
                        ];

                    }

                    file_put_contents($target_path . 'fileinfo.json', json_encode($fileinfos));
                    $cmd .= '"' . $target_path . 'fileinfo.json" ';
                    Log::LogInfo('VM: Create Backup gz: ' . $cmd);
                    exec($cmd);


                    Log::LogInfo('VM: Delete: ' . $target_path . 'fileinfo.json');
                    unlink($target_path . 'fileinfo.json');
 
                } else {
                    Log::LogWarning('VM: Compression "'. Config::$COMPRESS_TYPE .'" is not supported');
                }

                sleep(30);
                $this->commitSnapshot($snapshot_commit);
                sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'invalid compression type'), 'warning');
                Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                return false;

            // Backup ohne Kompression
            } else {

                $target_path = $target_path . date('Y-m-d_H.i') . '/';
                Log::LogInfo('VM: Backup without Compression to: ' . $target_path);
                mkdir($target_path, 0777, true);

                $fileinfos = [];
                foreach($copy_files as $file) {

                    $pi = pathinfo($file);
                    Log::LogDebug('VM: Copy File: ' . $file);
                    copy($file, $target_path . $pi['basename']);

                    $fileinfos[] = [
                        'File' => $file,
                        'InArchive' => $pi['basename'],
                        'Permissions' => substr(sprintf('%o', fileperms($file)), -4),
                        'User' => posix_getpwuid(fileowner($file))['name'],
                        'Group' => posix_getgrgid(filegroup($file))['name']
                    ];

                    file_put_contents($target_path . 'fileinfo.json', json_encode($fileinfos));

                }

            }
            

            // Snapshot zurückspielen, Benachrichtigung senden
            sleep(30);
            $this->commitSnapshot($snapshot_commit);
            sendNotification(sprintf(LANG_NOTIFY_END_BACKUP_VM, $this->name));
            Jobs::remove(JobCategory::VM, 'backup', 'backup_vm_' . $this->uuid);
            return true;

        // Backup erstellen OHNE Snapshot
        } else if($this->state == VMState::STATE_STOPPED) {

            Log::LogDebug('VM: Backup of offline maschine');

            // Nachricht senden
            sendNotification(sprintf(LANG_NOTIFY_START_BACKUP_VM, $this->name));

            // Job hinzufügen
            Jobs::add(JobCategory::VM, 'backup', $this->uuid);

            $this->loadUsedDisks();

            // Dateien bestimmen, die kopiert werden müssen.
            $copy_files = [];
            foreach($this->disks as $disk) {
                $copy_files[] = $disk['Source'];
                $pi = pathinfo($disk['Source']);
                $copy_files[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
                foreach($disk['PreSource'] as $pre) {
                    $copy_files[] = $pre;
                    $pi = pathinfo($pre);
                    $copy_files[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
                }
            }

            if(Config::$COMPRESS_BACKUP) {
                // Backup ZIP Kompression
                if(Config::$COMPRESS_TYPE == 'zip') {
                
                    
                    $target_filename = $target_path . date('Y-m-d_H.i') . '.zip';
                    Log::LogInfo('VM: Compress Backup to Zip: ' . $target_filename);

                    $zip = new ZipArchive();
                    if($zip->open($target_filename, ZipArchive::CREATE) !== true) {

                        sleep(30);
                        sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'invalid compression type'), 'warning');
                        Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                        return false;

                    }

                    $fileinfos = [];
                    foreach($copy_files as $file) {

                        Log::LogDebug('VM: Add File "'.$file.'" to Zip!');

                        $pi = pathinfo($file);
                        if(!$zip->addFile($file, $pi['basename'])) {
                            Log::LogError('VM: File "'.$file.'" could not add to Zip!');

                            sleep(30);
                            sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'file can not compress'), 'warning');
                            Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                            return false;

                        }

                        $fileinfos[] = [
                            'File' => $file,
                            'InArchive' => $pi['basename'],
                            'Permissions' => substr(sprintf('%o', fileperms($file)), -4),
                            'User' => posix_getpwuid(fileowner($file))['name'],
                            'Group' => posix_getgrgid(filegroup($file))['name']
                        ];

                    }
                    
                    $zip->addFromString('fileinfo.json', json_encode($fileinfos));

                    if(!$zip->close()) {
                        Log::LogError('VM: could not end Zipfile!');

                        sleep(30);
                        sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, 'can not write ZipFile'), 'warning');
                        Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                        return false;

                    }
                
                // Backup GZ Komprimiert
                } else if(Config::$COMPRESS_TYPE == 'tar.gz') {

                    $target_filename = $target_path . date('Y-m-d_H.i') . '.tar.gz';
                    Log::LogInfo('VM: Compress Backup to gz: ' . $target_filename);
                    Log::LogWarning('VM: Actualy... for this compression is no restore supported!');

                    $cmd = 'tar -czf "' . $target_filename . '" ';

                    $fileinfos = [];
                    foreach($copy_files as $file) {

                        $pi = pathinfo($file);
                        $cmd .= '"' . $file . '" ';

                        $fileinfos[] = [
                            'File' => $file,
                            'InArchive' => $pi['basename'],
                            'Permissions' => substr(sprintf('%o', fileperms($file)), -4),
                            'User' => posix_getpwuid(fileowner($file))['name'],
                            'Group' => posix_getgrgid(filegroup($file))['name']
                        ];

                    }

                    file_put_contents($target_path . 'fileinfo.json', json_encode($fileinfos));
                    $cmd .= '"' . $target_path . 'fileinfo.json" ';
                    Log::LogInfo('VM: Create Backup gz: ' . $cmd);
                    exec($cmd);


                    Log::LogInfo('VM: Delete: ' . $target_path . 'fileinfo.json');
                    unlink($target_path . 'fileinfo.json');
                
                // Backup ohne Kompression
                } else {

                    $target_path = $target_path . date('Y-m-d_H.i') . '/';
                    Log::LogInfo('VM: Backup without Compression to: ' . $target_path);
                    mkdir($target_path, 0777, true);
    
                    $fileinfos = [];
                    foreach($copy_files as $file) {
    
                        $pi = pathinfo($file);
                        Log::LogDebug('VM: Copy File: ' . $file);
                        copy($file, $target_path . $pi['basename']);
    
                        $fileinfos[] = [
                            'File' => $file,
                            'InArchive' => $pi['basename'],
                            'Permissions' => substr(sprintf('%o', fileperms($file)), -4),
                            'User' => posix_getpwuid(fileowner($file))['name'],
                            'Group' => posix_getgrgid(filegroup($file))['name']
                        ];
    
                        file_put_contents($target_path . 'fileinfo.json', json_encode($fileinfos));
    
                    }

                }

            }

        }

    }
    
}

class KVM {

    private Array|null $vms = null;
    
    /**
     * getVMs
     * Gibt alle aktuell eingerichteten Backups zurück. 
     * @param  mixed $force_reload true = lädt Daten neu, verwendet keine Cache Daten
     * @return array Array mit VM Objekten
     */
    public function getVMs($force_reload = false) {
        
        Log::LogDebug("KVM: loading VMs");

        if($this->vms === null || $force_reload) {
            $this->vms = [];

            $virsh_list_lines = [];

            exec("virsh list --all", $virsh_list_lines);

            unset($virsh_list_lines[0]);
            unset($virsh_list_lines[1]);
            unset($virsh_list_lines[array_key_last($virsh_list_lines)]);

            foreach($virsh_list_lines as $line) {
                preg_match('/([0-9\-]+)[ ]{2,}(.+)[ ]{2,}(.+)/', $line, $arr);
                
                if(count($arr) !== 4) {
                    continue;
                }

                $vm = new VM();
                if(trim($arr[1]) == '-') {
                    $vm->id = -1;
                } else {
                    $vm->id = (int)trim($arr[1]);
                }
                $vm->name = trim($arr[2]);
                $vm->state = VMState::tryFrom(trim($arr[3])) ?? VMState::STATE_UNKNOWN;
                
                $vm->getXML();
                $vm->loadUsedDisks();
                $vm->loadInfo();

                $this->vms[] = $vm;

            }
        }

        return $this->vms;
    }
    
}

?>