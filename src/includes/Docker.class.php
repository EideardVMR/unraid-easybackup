<?php

class Container {

    private $client = null;
    public $name = '';
    public $id = '';
    public $mounts = [];
    public $icon = '';
    public $state = '';

    function __construct($client)
    {
        $this->client = $client;
    }

    public function startContainer(){

        $response = $this->client->dispatchCommand('/containers/' . $this->id . '/start', []);

    }

    public function stopContainer(){
        
        $response  = $this->client->dispatchCommand('/containers/' . $this->id . '/stop', []);

    }    

    public function loadInformations(){

        $container = $this->client->dispatchCommand('/containers/'.$this->id.'/json');
        $this->mounts = $container['Mounts'];
        $this->icon = $container['Labels']['net.unraid.docker.icon'] ?? null;
        $this->state = $container['State']['Status'];

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

    public function createBackup() {

        Log::LogDebug('Container: Start Backup "' . $this->name . '"');

        $was_running = false;

        // Backup angehaltenen Docker
        if($this->state == 'running') {

            $was_running = true;

            $this->stopContainer();
            Log::LogInfo('Container: Stop Container "' . $this->name . '"...');
            $timeout = 0;
            do{
                $this->loadInformations();
                sleep(1);
                $timeout++;
                if($timeout > 30) {
                    Log::LogError('Container: Stop Container "' . $this->name . '" failed. Timeout!');
                    return false;
                }
            } while($this->state == 'running');

        }

        $copy_files = [];
        foreach($this->mounts as $mount) {

            // Ignoriere Mounts die nicht vom Type "bind" sind
            if($mount['Type'] != 'bind') { 
                Log::LogInfo('Container: Mount "' . $mount['Source'] . '" is not Bind!. Mount ignored');
                continue; 
            }

            if(in_array($mount['Source'], Config::$APPDATA_IGNORE_BINDES)) {
                Log::LogInfo('Container: Mount "' . $mount['Source'] . '" is disabled by user. Mount ignored');
                continue;
            }

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

        #print_debug($copy_files);

        $target_path = Config::$APPDATA_BACKUP_PATH . $this->name . '/' . date('Y-m-d_H.i');

        if(Config::$COMPRESS_BACKUP) {
            if(Config::$COMPRESS_TYPE == 'zip') {
                $this->BackupCompressZip($copy_files, $target_path);
            } else if(Config::$COMPRESS_TYPE == 'tar.gz') {
                $this->BackupCompressGz($copy_files, $target_path);
            } else {
                Log::LogWarning('Container: Compression "'. Config::$COMPRESS_TYPE .'" is not supported');
            }
        } else {
            $this->BackupUnCompressed($copy_files, $target_path);
        }

        if($was_running) {

            Log::LogInfo('Container: Start Container "' . $this->name . '"...');
            $this->startContainer();
            
            do{
                $this->loadInformations();
                sleep(1);
                $timeout++;
                if($timeout > 30) {
                    Log::LogError('Container: Start Container "' . $this->name . '" failed. Timeout!');
                    return false;
                }
            } while($this->state != 'running');

        }

    }

    private function BackupUnCompressed($target_files, $target_path){
        $target_path .= '/';
        Log::LogInfo('Container: Backup without Compression to: ' . $target_path);

    }

    private function BackupCompressZip($target_files, $target_path){
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

        foreach($target_files as $tf) {
            if(!$zip->addFile($tf['full_path'], $tf['r_path'])){
                Log::LogError('Container: Could not create "' . $tf['full_path'] . '"');
                return false;
            }
        }

        if(!$zip->addFromString('mounts.json', json_encode($this->mounts))) {
            Log::LogError('Container: Could not create mounts.json');
            return false;
        }

        if(!$zip->close()) {
            Log::LogError('Container: Could not close archive: ' . $target_path);
            return false;
        }

        return true;

    }

    private function BackupCompressGz($target_files, $target_path){
        $target_path .= '.tar.gz';
        Log::LogInfo('Container: Backup with GZ Compression to: ' . $target_path);

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
            
            $con = new Container($this->client);
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