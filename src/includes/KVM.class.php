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

    private $guestAgent = null;

    public $backup_compressioninfo = [];

    private $stored_backups = null;
        
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
            Log::LogDebug('VM: Load XML');
            $cmd = 'virsh dumpxml "' . $this->name . '"';
            cmdExec($cmd, $xml, $error);
            if(mb_strlen($error) > 0) {
                $this->error = $error;
                Log::LogError('VM: Loading XML failed');
                Log::LogError($error);
                return false;
            }
            $this->xml = $xml;
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
            if($disks[$i]->attributes()['device'] != 'disk') { continue; }
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

        Log::LogDebug('VM: Load VM Informations');
        $cmd = 'virsh dominfo "' . $this->name . '"';
        cmdExec($cmd, $output, $error);
        if(mb_strlen($error) > 0) {
            $this->error = $error;
            Log::LogError('VM: Loading VM informations failed');
            Log::LogError($error);
            return false;
        }
        $output = explode("\n", $output);
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

    public function checkGuestAgent(){
        LOG::LogDebug('VM: Check guestagent');
        if($this->guestAgent === null) {
            $cmd = 'virsh qemu-agent-command "' . $this->name . '" \'{"execute": "guest-info", "arguments": {}}\'';
            cmdExec($cmd, $exec_out, $error);
            if(mb_strlen($error) > 0) {
                $this->error = $error;
                Log::LogInfo('VM: guestagent not found');
                Log::LogInfo($error);
                return false;
            }
            $this->guestAgent = true;
        }

        return $this->guestAgent;
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

        // Prüfen ob GuestAgent installiert ist
        if(!$this->checkGuestAgent()){
            LOG::LogError('VM: Guest agent is not responding on "' . $this->name . '".');
            $this->error = "Guest agent is not responding";
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
        cmdExec($cmd, $exec_out, $error);
        if(mb_strlen($error) > 0) {
            $this->error = $error;
            Log::LogError('VM: Create Snapshot "' . Config::$SNAPSHOT_EXTENSION . $snapnumber . '" failed');
            Log::LogError($error);
            return false;
        }
        LOG::LogDebug("VM: Create Snapshot: " . $cmd);
        if('Domain snapshot ' . Config::$SNAPSHOT_EXTENSION . $snapnumber . " created\n" == $exec_out) {
            
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

        $this->error = $exec_out;
        Log::LogError('VM: Unknown Error: ' . $exec_out);
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
            cmdExec(join(' ', $command), $output, $error);
            if(mb_strlen($error) > 0) {
                $errors[] = 'Commit ' . $target . ' failed';
                Log::LogError('VM: commit failed');
                Log::LogError($error);
            }
        }

        if(count($errors) > 0) {
            $this->error = join('<br>', $errors);
            return false;
        }

        if($output != "\nSuccessfully pivoted\n") {
            $this->error = 'Unknown Error: ' . $output;
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
        cmdExec($cmd, $output, $error);
        if(mb_strlen($error) > 0) {
            $this->error = $error;
            Log::LogError('VM: revert failed');
            Log::LogError($error);
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
        cmdExec($cmd, $output, $error);
        if(mb_strlen($error) > 0) {
            $this->error = $error;
            Log::LogError('VM: Start VM failed');
            Log::LogError($error);
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
        cmdExec($cmd, $output, $error);
        if(mb_strlen($error) > 0) {
            $this->error = $error;
            Log::LogError('VM: Shutdown VM failed');
            Log::LogError($error);
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

        if($this->stored_backups !== null) {
            return $this->stored_backups;
        }

        $output = [];
        $files = [];
        if(file_exists(Config::$VM_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR)) {
            $files = scandir(Config::$VM_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR);
        }
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
    
    /**
     * createBackup
     * Erzeugt ein Backup
     * @param  mixed $notify sendet eine Nachricht an Unraid
     * @return bool
     */
    public function createBackup($notify = false) : bool {

        $snapshot_commit = 'original';

        Log::LogDebug('VM: Start Backup "' . $this->name . '"');
        // Ablehnen wenn VM nicht im definierten Status ist. 
        if($this->state != VMState::STATE_RUNNING && $this->state != VMState::STATE_STOPPED) {
            Log::LogInfo('VM: Backup of "' . $this->name . '" can not start in unsupported state. Backup aborted');
            $this->error = "VM must be started or stopped to backup it.";
            // Nachricht senden
            if($notify) {
                sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, LANG_NOTIFY_INVALID_VM_STATE));
            }
            return false;
        }
        
        // Lade Informationen (um die letzte Snapshotversion zu erhalten)
        $this->loadInfo();

        // Prüfen ob der Job bereits läuft.
        if(Jobs::check(JobCategory::VM, 'backup', $this->uuid)) {
            Log::LogInfo('VM: Backup of "' . $this->name . '" is running. Backup aborted.');
            $this->error = 'Job is running';
            return true;
        }
        Jobs::add(JobCategory::VM, 'backup', $this->uuid);

        // Backuppfad bestimmen
        $target_path = Config::$VM_BACKUP_PATH . $this->name . '/' . date('Y-m-d_H.i');
        Log::LogInfo('VM: determine Backuppath: ' . $target_path);

        // Dateien zum Backup ermitteln
        $copy_files = [];

        // Nachricht senden
        if($notify) {
            sendNotification(sprintf(LANG_NOTIFY_START_BACKUP_VM, $this->name));
        }

        // Snapshot erstellen und Dateien ermitteln
        if($this->state == VMState::STATE_RUNNING) {
            if($this->lastSnapshotNumber !== null) {
                $snapshot_commit = Config::$SNAPSHOT_EXTENSION . $this->lastSnapshotNumber;
            }
            Log::LogDebug('VM: After Backup commit to ' . $snapshot_commit);

            if(!$this->createSnapshot()){
                if($notify) {
                    sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, $this->error), 'warning');
                }
                
                Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                return false;
            }
            // Schlafen, damit alle Informationen stimmen.
            sleep(5);

            // Dateien zusammensuchen
            foreach($this->disks as $disk) {

                $exists_xml_paths = [];
                foreach($disk['PreSource'] as $pre) {
                    
                    $full_path = $pre;
                    $pi = pathinfo($pre);

                    $copy_files[] = [
                        'full_path' => $full_path,
                        'r_path' => $pi['basename'],
                        'source' => $pi['dirname'] . '/'
                    ];

                    if(in_array($pi['dirname'] . '/' . $pi['extension'] . '.xml', $exists_xml_paths)) { continue; }
                    $copy_files[] = [
                        'full_path' => $pi['dirname'] . '/' . $pi['extension'] . '.xml',
                        'r_path' =>  $pi['extension'] . '.xml',
                        'source' => $pi['dirname'] . '/'
                    ];
                    
                    $exists_xml_paths[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';
                }

            }

        // Nur Dateien ermitteln
        } else if($this->state == VMState::STATE_STOPPED) {

            foreach($this->disks as $disk) {
                    
                $full_path = $disk['Source'];
                $pi = pathinfo($disk['Source']);

                $copy_files[] = [
                    'full_path' => $full_path,
                    'r_path' => $pi['basename'],
                    'source' => $pi['dirname'] . '/'
                ];

                $copy_files[] = [
                    'full_path' => $pi['dirname'] . '/' . $pi['extension'] . '.xml',
                    'r_path' =>  $pi['extension'] . '.xml',
                    'source' => $pi['dirname'] . '/'
                ];

                $exists_xml_paths = [];
                foreach($disk['PreSource'] as $pre) {
                    
                    $full_path = $pre;
                    $pi = pathinfo($pre);

                    $copy_files[] = [
                        'full_path' => $full_path,
                        'r_path' => $pi['basename'],
                        'source' => $pi['dirname'] . '/'
                    ];

                    if(in_array($pi['dirname'] . '/' . $pi['extension'] . '.xml', $exists_xml_paths)) { continue; }
                    $copy_files[] = [
                        'full_path' => $pi['dirname'] . '/' . $pi['extension'] . '.xml',
                        'r_path' =>  $pi['extension'] . '.xml',
                        'source' => $pi['dirname'] . '/'
                    ];
                    
                    $exists_xml_paths[] = $pi['dirname'] . '/' . $pi['extension'] . '.xml';

                }

            }

        }

        $backupstate = false;
        // Backup mit kompression
        if(Config::$COMPRESS_BACKUP) {

            if(Config::$COMPRESS_TYPE == 'zip') {

                $backupstate = $this->BackupCompressZip($copy_files, $target_path);

            } else if(Config::$COMPRESS_TYPE == 'tar.gz') {

                $backupstate = $this->BackupCompressGz($copy_files, $target_path);

            }

        } else {

            $backupstate = $this->BackupUnCompressed($copy_files, $target_path);

        }

        // Snapshot commit
        if($this->state == VMState::STATE_RUNNING) {
            if(!$this->commitSnapshot($snapshot_commit)) {
        
                // Nachricht senden
                if($notify) {
                    sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_VM, $this->name, LANG_NOTIFY_SNAPSHOT_COMMIT_FAILED), 'warning');
                }
                
                Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
                return false;
            }
        }

        // Nachricht senden
        if($notify) {
            sendNotification(sprintf(LANG_NOTIFY_END_BACKUP_VM, $this->name));
        }

        Jobs::remove(JobCategory::VM, 'backup', $this->uuid);
        return $backupstate;

    }
        
    /**
     * BackupUnCompressed
     * Kopiert alle Dateien ohne Komprimierung
     * @param  mixed $target_files Array mit den Dateien die kopiert werden.
     * Jedes Array muss folgendes enthalten: ['full_path' => Voller Pfad zur Datei, 'r_path' => Pfad im Backup]
     * @param  mixed $target_path Pfad zum Ziel
     * @return bool true wenn es geklappt hat, sonst false
     */
    private function BackupUnCompressed($target_files, $target_path) : bool {
        
        $this->backup_compressioninfo = [
            'Files' => 0,
            'OriginalSize' => 0,
            'CompressedSize' => 0,
            'Time' => 0
        ];

        $target_path .= '/';
        Log::LogInfo('VM: Backup without Compression to: ' . $target_path);

        if(!CheckFilesExists($target_path)) {
            mkdir($target_path, 0777, true);
        }

        $starttime = time();
        foreach($target_files as $tf) {
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += filesize($tf['full_path']);
            $this->backup_compressioninfo['CompressedSize'] += filesize($tf['full_path']);

            $pi = pathinfo($target_path . $tf['r_path']);
            if(!CheckFilesExists($pi['dirname'] . '/')) {
                mkdir($pi['dirname'] . '/', 0777, true);
            }

            if(!copy($tf['full_path'], $target_path . $tf['r_path'])) {
                Log::LogError('VM: Could not create "' . $tf['full_path'] . '"');
                return false;
            }
        }

        if(file_put_contents($target_path . 'fileinfo.json', json_encode($this->getFileInfos($target_files))) === false){
            Log::LogError('VM: Could not create fileinfo.json');
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += strlen(json_encode($this->getFileInfos($target_files)));
            return false;
        }
        $this->backup_compressioninfo['Time'] = time() - $starttime;

        return true;

    }
    
    /**
     * BackupCompressZip
     * Kopiert alle Dateien in eine ZipDatei
     * @param  mixed $target_files Array mit den Dateien die kopiert werden.
     * Jedes Array muss folgendes enthalten: ['full_path' => Voller Pfad zur Datei, 'r_path' => Pfad im Backup]
     * @param  mixed $target_path Pfad zum Ziel
     * @return bool true wenn es geklappt hat, sonst false
     */
    private function BackupCompressZip($target_files, $target_path) : bool {
        
        $this->backup_compressioninfo = [
            'Files' => 0,
            'OriginalSize' => 0,
            'CompressedSize' => 0,
            'Time' => 0
        ];

        $target_path .= '.zip';
        $pi = pathinfo($target_path);
        if(!CheckFilesExists($pi['dirname'] . '/')) {
            mkdir($pi['dirname'] . '/', 0777, true);
        }

        Log::LogInfo('VM: Backup with Zip Compression to: ' . $target_path);

        $zip = new ZipArchive();
        if(!$zip->open($target_path, ZipArchive::CREATE)) {
            return false;
        }

        $starttime = time();
        foreach($target_files as $tf) {
            
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += filesize($tf['full_path']);
            
            if(!$zip->addFile($tf['full_path'], $tf['r_path'])){
                Log::LogError('VM: Could not create "' . $tf['full_path'] . '"');
                return false;
            }
        }

        if(!$zip->addFromString('fileinfo.json', json_encode($this->getFileInfos($target_files)))) {
            Log::LogError('VM: Could not create fileinfo.json');
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += strlen(json_encode($this->getFileInfos($target_files)));
            return false;
        }

        if(!$zip->close()) {
            Log::LogError('VM: Could not close archive: ' . $target_path . "\nError: ". $zip->getStatusString());
            return false;
        }

        $this->backup_compressioninfo['CompressedSize'] += filesize($target_path);
        $this->backup_compressioninfo['Time'] = time() - $starttime;

        return true;

    }
    
    /**
     * BackupCompressGz
     * Kopiert alle Dateien ohne Komprimierung
     * @param  mixed $target_files Array mit den Dateien die kopiert werden.
     * Jedes Array muss folgendes enthalten: ['full_path' => Voller Pfad zur Datei, 'r_path' => Pfad im Backup, 'source' => Pfad innerhaln des Backups ohne Datei ]
     * @param  mixed $target_path Pfad zum Ziel
     * @return bool true wenn es geklappt hat, sonst false
     */
    private function BackupCompressGz($target_files, $target_path) : bool{

        $this->backup_compressioninfo = [
            'Files' => 0,
            'OriginalSize' => 0,
            'CompressedSize' => 0,
            'Time' => 0
        ];

        $target_path .= '.tar.gz';
        $pi = pathinfo($target_path);
        if(!CheckFilesExists($pi['dirname'] . '/')) {
            mkdir($pi['dirname'] . '/', 0777, true);
        }

        Log::LogInfo('VM: Backup with GZ Compression to: ' . $target_path);

        $command = 'tar -czf ' . $target_path;       
        foreach($target_files as $tf) {
            
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += filesize($tf['full_path']);

            $command .= ' -C "' . $tf['source'] . '" "' . $tf['r_path'] . '"';

        }

        if(file_put_contents($pi['dirname'] . '/fileinfo.json', json_encode($this->getFileInfos($target_files))) === false){
            Log::LogError('VM: Could not create fileinfo.json');
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += filesize($pi['dirname'] . '/fileinfo.json');
            return false;
        }
        $command .= ' -C "' . $pi['dirname'] . '/" "fileinfo.json"';

        Log::LogDebug('Start Backup: ' . $command);

        $starttime = time();        
        cmdExec($command, $exec_output, $error);
        if(mb_strlen($error) > 0) {
            $this->error = $error;
            Log::LogError('VM: Compression failed');
            Log::LogError($error);
            return false;
        }

        unlink($pi['dirname'] . '/mounts.json');
        unlink($pi['dirname'] . '/fileinfo.json');

        if(count($exec_output)>0) {
            return false;
        }

        $this->backup_compressioninfo['CompressedSize'] += filesize($target_path);
        $this->backup_compressioninfo['Time'] = time() - $starttime;

        return true;

    }
    
    /**
     * getFileInfos
     * Erstellt für alle Dateien die im Backup enthalten sind Informationen über den inhaber und Dateiberechtigungn
     * @param  mixed $target_files Array mit den Dateien die kopiert werden.
     * Jedes Array muss folgendes enthalten: ['full_path' => Voller Pfad zur Datei, 'r_path' => Pfad im Backup]
     * @return array  mit entsprechenden den Informationen [
     * 'File' => Originaler Pfad zur Datei
     * 'InArchive' => Pfad im Archiv
     * 'Permissions' => Dateiberechtigungen (z.B. 0777)
     * 'User' => Benutzer Inhaber (z.B. root)
     * 'Group' => Grupper Inhaber (z.B. user)
     * ]
     */
    function getFileInfos($target_files) : array {
        $fileinfos = [];
        foreach($target_files as $file) {
    
            $pi = pathinfo($file['full_path']);
    
            $fileinfos[] = [
                'File' => $file['full_path'],
                'InArchive' => $file['r_path'],
                'Permissions' => substr(sprintf('%o', fileperms($file['full_path'])), -4),
                'User' => posix_getpwuid(fileowner($file['full_path']))['name'],
                'Group' => posix_getgrgid(filegroup($file['full_path']))['name']
            ];
    
        }
    
        return $fileinfos;
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

            $virsh_list_lines = '';

            cmdExec("virsh list --all", $virsh_list_lines, $error);
            if(mb_strlen($error) > 0) {
                Log::LogError('VM: Start VM failed');
                Log::LogError($error);
                return false;
            }
            $virsh_list_lines = explode("\n", $virsh_list_lines);

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