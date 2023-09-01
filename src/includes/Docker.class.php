<?php

class Container {

    private $client = null;
    public $name = '';
    public $id = '';
    public $mounts = [];
    public $icon = '';
    public $state = '';

    public $backup_compressioninfo = [];

    function __construct()
    {

    }

    function getClient() {
        if($this->client === null) {
            $this->client = new DockerClient('/var/run/docker.sock');
        }

        return $this->client;
    }

    public function startContainer(){

        Log::LogDebug('Container: Start Container "' . $this->name . '"');
        $this->client = new DockerClient('/var/run/docker.sock');
        $response = $this->client->dispatchCommand('/containers/' . $this->id . '/start', []);
        Log::LogInfo(print_r($response, true));

    }

    public function stopContainer(){
        
        Log::LogDebug('Container: Stop Container "' . $this->name . '"');
        $this->client = new DockerClient('/var/run/docker.sock');
        $response  = $this->client->dispatchCommand('/containers/' . $this->id . '/stop', []);
        Log::LogInfo(print_r($response, true));
    }    

    public function loadInformations(){

        Log::LogDebug('Container: Load informations of "' . $this->name . '"');

        $this->client = new DockerClient('/var/run/docker.sock');
        $dockerContainers  = $this->client->dispatchCommand('/containers/json?all=1');
        foreach($dockerContainers as $dc) {
            if(($dc['Id'] ?? null) == $this->id) {
                $this->mounts = $dc['Mounts'];
                $this->icon = $dc['Labels']['net.unraid.docker.icon'] ?? null;
                $this->state = $dc['State'];
                Log::LogDebug('Container: Informations of "' . $this->name . '" found');
                return true;
            }
        }
        
        Log::LogError('Container: Informations of "' . $this->name . '" not found.');
        Log::LogError(print_r($dockerContainers, true));
        return false;

        /*
        # Dieser Part sollte eigentlich funtionieren, aber ich erhalte immer "Page not Found"
        # Fehler wurde noch nicht gefunden!
        
        $this->client = new DockerClient('/var/run/docker.sock');
        $container = $this->client->dispatchCommand('/containers/'.$this->id.'/json');

        if(
            $container['Mounts'] ?? true ||
            $container['Labels']['net.unraid.docker.icon'] ?? true ||
            $container['State']['Status'] ?? true
        ) {
            Log::LogError('Container: Load Informations failed by "'.$this->name.'"');
            return false;
        }
        
        $this->mounts = $container['Mounts'] ?? [];
        $this->icon = $container['Labels']['net.unraid.docker.icon'] ?? null;
        $this->state = $container['State']['Status'] ?? 'unknown';
        Log::LogDebug('Container: Informations of "' . $this->name . '" found');
        return true;
        */
    }

    /**
     * getStoredBackups
     * Gibt Informationen für die gespeicherten Backups zurück
     * @return array 
     */
    public function getStoredBackups() : array {

        $output = [];
        $files = scandir(Config::$APPDATA_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR);
        foreach($files as $file) {
            if($file == '.' || $file == '..') { continue; }

            $pi = pathinfo(Config::$APPDATA_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR . $file);

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
                'Size' => filesize(Config::$APPDATA_BACKUP_PATH . $this->name . DIRECTORY_SEPARATOR . $file),
                'BackupType' => $backuptype
            ];

            $output[] = $o;

        }

        usort($output, function($a, $b) {
            return $b['TimestampUnix'] <=> $a['TimestampUnix'];
        });

        return $output;
    }

    public function createBackup($notify = false) {

        Log::LogDebug('Container: Start Backup "' . $this->name . '"');

        // Benachrichtigung senden wenn gewünscht
        if($notify) {
            sendNotification(sprintf(LANG_NOTIFY_START_BACKUP_CONTAINER, $this->name), 'normal');
        }

        // Job prüfen und erstellen
        if(Jobs::check(JobCategory::CONTAINER, 'backup', $this->id)) {
            Log::LogWarning('Container: Aborted Backup of "' . $this->name . '" is running.');
            return false;
        }
        Jobs::add(JobCategory::CONTAINER, 'backup', $this->id);

        // Docker anhalten wenn dieser läuft.
        $was_running = false;
        if($this->state == 'running') {

            $was_running = true;

            $this->stopContainer();
            $timeout = 0;
            do{
                $this->loadInformations();
                sleep(1);
                $timeout++;
                if($timeout > 30) {
                    Log::LogError('Container: Stop Container "' . $this->name . '" failed. Timeout!');
                    Jobs::remove(JobCategory::CONTAINER, 'backup', $this->id);if($notify) {
                        sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_CONTAINER, $this->name, LAMG_MSG_CONTAINER_TIMEOUT_FOR_STOP), 'warning');
                    }
                    return false;
                }
            } while($this->state != 'exited');

        }

        // Dateien für das Backup ermitteln
        $copy_files = [];
        foreach($this->mounts as $mount) {

            // Ignoriere Mounts die nicht vom Type "bind" sind
            if($mount['Type'] != 'bind') { 
                Log::LogInfo('Container: Mount "' . $mount['Source'] . '" is not Bind!. Mount ignored');
                continue; 
            }

            // Ignoriere Mounts seitens der User
            if(in_array($mount['Source'], Config::$APPDATA_IGNORE_BINDES)) {
                Log::LogInfo('Container: Mount "' . $mount['Source'] . '" is disabled by user. Mount ignored');
                continue;
            }

            // Dateien zusammensuchen
            $mountfiles = scandirRecursive($mount['Source']);
            foreach($mountfiles as $mf) {
                $full_path = $mf;

                $ex = explode('/',$mount['Source']);
                unset($ex[array_key_last($ex)]);                
                $r_path = str_replace(join('/',$ex) . '/', '' , $mf);

                $copy_files[] = [
                    'full_path' => $full_path,
                    'r_path' => $r_path
                ];
            }

            Log::LogInfo('Container: Backup ' . count($mountfiles) . ' files of "' . $mount['Source'] . '"');

        }

        // Zielpfad bestimmen 
        // Dateiendung und tieferliegende Verzeichnisse werden in der Kompressionmethode angehangen!
        // Daher kein / am ende des Pfades.
        $target_path = Config::$APPDATA_BACKUP_PATH . $this->name . '/' . date('Y-m-d_H.i');

        $backupstate = false;
        if(Config::$COMPRESS_BACKUP) {
            if(Config::$COMPRESS_TYPE == 'zip') {
                $backupstate = $this->BackupCompressZip($copy_files, $target_path);
            } else if(Config::$COMPRESS_TYPE == 'tar.gz') {
                $backupstate = $this->BackupCompressGz($copy_files, $target_path);
            } else {
                Log::LogWarning('Container: Compression "'. Config::$COMPRESS_TYPE .'" is not supported');
            }
        } else {
            $backupstate = $this->BackupUnCompressed($copy_files, $target_path);
        }

        Jobs::remove(JobCategory::CONTAINER, 'backup', $this->id);

        // Docker starten wenn dieser zuvor online war
        if($was_running) {

            $this->startContainer();
            
            do{
                $this->loadInformations();
                sleep(1);
                $timeout++;
                if($timeout > 30) {
                    Log::LogError('Container: Start Container "' . $this->name . '" failed. Timeout!');
                    Jobs::remove(JobCategory::CONTAINER, 'backup', $this->id);
                    if($notify) {
                        sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_CONTAINER, $this->name, LAMG_MSG_CONTAINER_TIMEOUT_FOR_START), 'warning');
                    }
                    return false;
                }
            } while($this->state != 'running');

        }

        // Benachrichtigung senden wenn gewünscht
        if($notify) {

            if($backupstate) {
                Log::LogInfo('Container: Backup ' . $this->name .' completed');
                $compressinfo  = '' . $this->backup_compressioninfo['Files'] . ' ' . LANG_GUI_FILES . ' ';    
                $tz = date_default_timezone_get();
                date_default_timezone_set('UTC');
                $compressinfo .= sprintf(LANG_GUI_IN_TIME, date('H:i:s', $this->backup_compressioninfo['Time'])) . '<br>';
                date_default_timezone_set($tz);
                Log::LogDebug(print_r($this->backup_compressioninfo, true));
                sendNotification(sprintf(LANG_NOTIFY_END_BACKUP_CONTAINER, $this->name, $compressinfo), 'normal');
            } else {
                Log::LogError('Container: Backup ' . $this->name .' failed');
                sendNotification(sprintf(LANG_NOTIFY_FAILED_BACKUP_CONTAINER, $this->name), 'warning');
            }
        }

        return $backupstate;

    }

    private function BackupUnCompressed($target_files, $target_path){
        
        $this->backup_compressioninfo = [
            'Files' => 0,
            'OriginalSize' => 0,
            'CompressedSize' => 0,
            'Time' => 0
        ];

        $target_path .= '/';
        Log::LogInfo('Container: Backup without Compression to: ' . $target_path);

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
                Log::LogError('Container: Could not create "' . $tf['full_path'] . '"');
                return false;
            }
        }

        if(file_put_contents($target_path . 'mounts.json', json_encode($this->mounts)) === false){
            Log::LogError('Container: Could not create mounts.json');
            return false;
        }

        if(file_put_contents($target_path . 'fileinfo.json', json_encode($this->getFileInfos($target_files))) === false){
            Log::LogError('Container: Could not create fileinfo.json');
            return false;
        }
        $this->backup_compressioninfo['Time'] = time() - $starttime;

        return true;

    }

    private function BackupCompressZip($target_files, $target_path){
        
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

        Log::LogInfo('Container: Backup with Zip Compression to: ' . $target_path);

        $zip = new ZipArchive();
        if(!$zip->open($target_path, ZipArchive::CREATE)) {
            return false;
        }

        $starttime = time();
        foreach($target_files as $tf) {
            
            $this->backup_compressioninfo['Files']++;
            $this->backup_compressioninfo['OriginalSize'] += filesize($tf['full_path']);
            
            if(!$zip->addFile($tf['full_path'], $tf['r_path'])){
                Log::LogError('Container: Could not create "' . $tf['full_path'] . '"');
                return false;
            }
        }

        if(!$zip->addFromString('mounts.json', json_encode($this->mounts))) {
            Log::LogError('Container: Could not create mounts.json');
            return false;
        }

        if(!$zip->addFromString('fileinfo.json', json_encode($this->getFileInfos($target_files)))) {
            Log::LogError('Container: Could not create fileinfo.json');
            return false;
        }

        if(!$zip->close()) {
            Log::LogError('Container: Could not close archive: ' . $target_path);
            return false;
        }

        $this->backup_compressioninfo['CompressedSize'] += filesize($target_path);
        $this->backup_compressioninfo['Time'] = time() - $starttime;

        return true;

    }

    private function BackupCompressGz($target_files, $target_path){
        $target_path .= '.tar.gz';
        Log::LogInfo('Container: Backup with GZ Compression to: ' . $target_path);

    }

    function getFileInfos($target_files){
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

class Docker {

    private $client = null;
    private $containers = [];

    public function __construct()
    {
        $this->client = new DockerClient('/var/run/docker.sock');
    }

    public function getContainers(){
        $dockerContainers  = $this->client->dispatchCommand('/containers/json?all=1');

        $this->containers = [];
        foreach ($dockerContainers as $container) {
            
            $con = new Container();
            $con->name = $container['Name'];
            $con->id = $container['Id'];
            $con->mounts = $container['Mounts'];
            $con->icon = $container['Labels']['net.unraid.docker.icon'] ?? null;
            $con->state = $container['State'];
            
            $this->containers[] = $con;

        }

        return $this->containers;
    }

}

?>