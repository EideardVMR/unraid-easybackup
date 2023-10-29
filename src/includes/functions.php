<?php
define('COLOR_DEFAULT', 'default');
define('COLOR_RED', 'red');
define('COLOR_GREEN', 'green');
define('COLOR_ORANGE', 'orange');
define('COLOR_CYAN', 'cyan');
define('COLOR_BLUE', 'blue');
define('COLOR_MAGENTA', 'magenta');
define('COLOR_BLACK', 'black');

/**
 * Erzeugt eine lesbare ausgabe eines Objektes
 * @param mixed $debug Objekt welches visualisiert werden soll.
 */
function print_debug($debug){
    echo str_replace(array('&lt;?php&nbsp;','?&gt;'), '', highlight_string( '<?php '. var_export($debug, true) .' ?>', true ) ).'<br>';
}

function sendNotification($message, $type = 'normal'){
    if($type != 'normal' && $type != 'alert' && $type != 'warning') { $type = 'warning'; }
    cmdExec('/usr/local/emhttp/webGui/scripts/notify -i ' . $type . ' -d "' . $message . '" -s "' . NAME . '"', $exec_out, $error);
    Log::LogInfo('Create Message: ' . $message);
    if(strlen($exec_out) > 0) {
        Log::LogInfo('Notify result: ' . $exec_out);
    }
    if(strlen($error) > 0) {
        Log::LogError('Notify error result: ' . $exec_out);
    }
}

function padit($c = 1){
    $o = '';
    for($i = 0; $i < $c; $i++) {
        $o .= '&nbsp;';
    }
    return $o;
}

/**
 * PrintScreen
 * Gibt Informationen in der CLI aus.
 * @param  string $text Ausgegebener Text
 * @param  string $text_color Farbe des Textes
 * @param  string $bg_color Hintergrundfarbe des Textes
 * @param  int $str_pad Verlängert die Zeichenkette bis zu dieser Position
 * @param  bool $ln Macht einen Umbruch in die nächste Zeile
 * @return void
 */
function PrintScreen($text, $text_color = COLOR_DEFAULT, $bg_color = COLOR_DEFAULT, $str_pad = 0, $ln = true) {

    $text_color_cli = "\033[39m";
    $bg_color_cli = "\033[49m";

    switch ($text_color) {
        case COLOR_RED:
            $text_color_cli = "\033[91m";
            break;
        case COLOR_GREEN:
            $text_color_cli = "\033[92m";
            break;
        case COLOR_ORANGE:
            $text_color_cli = "\033[93m";
            break;
        case COLOR_CYAN:
            $text_color_cli = "\033[96m";
            break;
        case COLOR_BLUE:
            $text_color_cli = "\033[94m";
            break;
        case COLOR_MAGENTA:
            $text_color_cli = "\033[95m";
            break;
        case COLOR_BLACK:
            $text_color_cli = "\033[30m";
            break;
    }

    switch ($bg_color) {
        case COLOR_RED:
            $bg_color_cli = "\033[101m";
            break;
        case COLOR_GREEN:
            $bg_color_cli = "\033[102m";
            break;
        case COLOR_ORANGE:
            $bg_color_cli = "\033[103m";
            break;
        case COLOR_CYAN:
            $bg_color_cli = "\033[106m";
            break;
        case COLOR_BLUE:
            $bg_color_cli = "\033[104m";
            break;
        case COLOR_MAGENTA:
            $bg_color_cli = "\033[105m";
            break;
        case COLOR_BLACK:
            $bg_color_cli = "\033[40m";
            break;
    }

    if($str_pad > 0) {
        $text = str_pad($text, $str_pad);
    }
    echo $bg_color_cli.$text_color_cli.$text . "\033[0m";
    if($ln) {
        echo "\n";
    }

}

/**
 * Progressbar
 * Erstellt eine Fortschrittsleiste
 * @param  int $current Aktueller Wert
 * @param  int $max Maximaler Wert
 * @return void
 */
function Progressbar($current, $max, $mbs = null) {
    if($max == 0) {
        $percent = 100;
    } else {
        $percent = $current * 50 / $max;
    }
    echo "\r\033[107m\033[30m";
    $bar = '';

    for($i = 0; $i < $percent; $i++) {
        $bar .= '#';
    }

    echo str_pad($bar, 50);
    echo ' | ';
    echo $current . ' of ' . $max;
    echo ' | ';
    echo floor($percent) . '%';
    if($mbs !== null) {
        echo ' | ' . number_format($mbs, 1, ',', '.') . 'MB/s';
    }
}


/**
 * CopyFile
 * Kopiert eine Datei
 * @param  string $source Quelldatei
 * @param  string $destination Zieldatei
 * @param  bool $progress Druckt eine Fortschrittsanzeige
 * @param  bool $delete_source_after_copy Entfernt die Datei nach dem erfolgreichen kopieren.
 * @return void
 */
function CopyFile($source, $destination, $progress = true, $delete_source_after_copy = false, $never_move = false){

    global $errors;

    $move_file = false;

    preg_match('/^\/mnt\/user\//', $source, $o_source);
    preg_match('/^\/mnt\/user\//', $destination, $o_destination);

    if(count($o_source) && count($o_destination)) {

        $disks = getDisks();

        foreach($disks as $disk) {
    
            $x_source = preg_replace('/^\/mnt\/user\//', '/mnt/'.$disk.'/', $source);
    
            if(CheckFilesExists($x_source)) {
    
                $move_file = $disk;
                break;
            }

        }

    }

    if($move_file !== false && !$never_move) {

        $x_source = preg_replace('/^\/mnt\/user\//', '/mnt/'.$disk.'/', $source);
        $x_destination = preg_replace('/^\/mnt\/user\//', '/mnt/'.$disk.'/', $destination);

        $pathinfo = pathinfo($x_destination);

        if(!CheckFilesExists($pathinfo['dirname'].'/')) {
            if(!mkdir($pathinfo['dirname'] . '/', 0777, true)){
                Log::LogError(sprintf(LANG_FAIL_CREATE_DIRECTORY, $pathinfo['dirname'] . '/'));
                return false;
            }
        }

        
        PrintScreen(sprintf("    Move \"%s\"\n        to \"%s\"", $x_source, $x_destination));

        if(rename($x_source, $x_destination)) {
            $size = filesize($x_source);
            Progressbar($size, $size, $size / 1e6);
            PrintScreen('');
            return true;
        } else {
            Log::LogError(sprintf(LANG_FAIL_FILE_MOVE, $x_source, $x_destination), __LINE__);
        }

        return false;

    } else {
        
        PrintScreen(sprintf("    Copy \"%s\"\n        to \"%s\"", $source, $destination));

    }
    

    $source_handle = fopen($source, 'r');
    $destination_handle = fopen($destination, 'w');
    
    if($source_handle === false) { return false; $errors[] = 'File "' . $source . '" not exists'; }
    if($destination_handle === false) { return false; $errors[] = 'File "' . $destination . '" not exists'; }
    
    $filesize = filesize($source);

    $read_bytes = 0;
    $mbs = 0;
    $x_time = 0;
    $x_bytes = 0;
    while(!feof($source_handle)) {

        if($x_time == 0){
            $x_time = time();
            $x_bytes = $read_bytes;
        }

        $buffer = fread($source_handle, 2048 * 100);
        fwrite($destination_handle, $buffer);
        $read_bytes += strlen($buffer);

        if((time() - $x_time) >= 3) {
            $mbs = ($read_bytes - $x_bytes) / 1e+6 / ( time() - $x_time );
            $x_time = 0;
        }
        
        if($progress){
            Progressbar($read_bytes, $filesize, $mbs);
        }
      
    }
    fclose($source_handle);
    fclose($destination_handle);

    if($progress){
        PrintScreen('');
    }

    if($delete_source_after_copy) {
        if(!unlink($source)){
            $msg = '['.__LINE__.']Could not delete file: ' . $source;
            PrintScreen($msg, 'red');
            $errors[] = $msg;
        }
    }

    return true;
}

function scandirRecursive($dir, &$results = array()) {

    $files = scandir($dir);
    //Log::LogInfo("scandir Start: " . $dir);

    foreach ($files as $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (is_file($path)) {
            //Log::LogInfo("scandir File: " . $path);
            $results[] = $path;
        } else if (is_dir($path) && $value != "." && $value != "..") {
            //Log::LogInfo("scandir Dir: " . $path);
            scandirRecursive($path, $results);
            //$results[] = $path;
        }
    }

    return $results;
}

function DeleteDirectory(){
    /*
    mkdir($dest, 0755);
    foreach (
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        ) as $item
    ) {
        if ($item->isDir()) {
            mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
        } else {
            rename($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
        }
    }
    */
}

function DeleteFile($file){
    if(Config::$ENABLE_RECYCLE_BIN) {
        
        $ex = explode('/',Config::$RECYCLE_BIN_PATH);
        unset($ex[count($ex)-2]);
        $rpath = Config::$RECYCLE_BIN_PATH . str_replace(join('/', $ex), '', $file);

        $pi = pathinfo($rpath);

        if(!file_exists($pi['dirname'])) {
            if(!mkdir($pi['dirname'], 0777, true)) {
                return false;
            }
        }
        
        return rename($file, $rpath);

    } else {
        if(CheckFilesExists($file)) {
            return unlink($file);
        } else {
            return false;
        }
    }
}

/**
 * CheckFilesExists
 * Prüft ob eine Datei/Ordner existiert.
 * @param  string $path
 * @return void
 */
function CheckFilesExists($path){
    clearstatcache();
    return file_exists($path);
}

/**
 * GotifyPush
 *
 * @param  mixed $message
 * @param  mixed $title
 * @param  mixed $priority
 * @return void
 */
function GotifyPush($message, $title, $priority = 2){
    $url = Config::$GOTIFY_SERVER . 'message?token=' . Config::$GOTIFY_TOKEN;
    $data = array('message' => $message, 'title' => $title, 'priority' => $priority);
    
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) {  }
}

function getSamePath(string $path1, string $path2) {

    $path1 = mb_str_split($path1);
    $path2 = mb_str_split($path2);
    
    foreach($path1 as $key => $char) {
        if($char == $path2[$key]) { continue; }
        break;
    }

    $tmp = array_slice($path1, 0, $key);
    return implode('', $tmp);
}

function RemoveBackup($full_path){

    if(is_file($full_path)) {

        if( Config::$ENABLE_RECYCLE_BIN ) {

            if( !CheckFilesExists( Config::$RECYCLE_BIN_PATH ) ) {
                
                if( mkdir( Config::$RECYCLE_BIN_PATH ) ) {

                    Log::LogError(sprintf(LANG_FAIL_CREATE_DIRECTORY, Config::$RECYCLE_BIN_PATH), __LINE__);
                    return;

                }

            }

            $same_path = getSamePath($full_path, Config::$RECYCLE_BIN_PATH);
            $r_path = str_replace($same_path, '', $full_path);
            if( substr($r_path, 0, 1) == '/'){
                $r_path = substr($r_path, 1);
            }

            #PrintScreen(Config::$RECYCLE_BIN_PATH . $r_path);
            $move_to = Config::$RECYCLE_BIN_PATH . $r_path;
            $pi = pathinfo($move_to);

            Log::LogInfo('Recycle: "' . $full_path . '" --> "' . $move_to . '"');

            exec('mkdir -p -v "' . $pi['dirname'] . '"', $exec_out);
            exec('mv "' . $full_path . '" "' . $move_to . '"', $exec_out);

        } else {

            Log::LogInfo('Remove: "' . $full_path . '');
            exec('rm "' . $full_path . '"');

        }

    } else {

        if( Config::$ENABLE_RECYCLE_BIN ) {

            if( !CheckFilesExists( Config::$RECYCLE_BIN_PATH ) ) {
                
                if( mkdir( Config::$RECYCLE_BIN_PATH ) ) {

                    Log::LogError(sprintf(LANG_FAIL_CREATE_DIRECTORY, Config::$RECYCLE_BIN_PATH), __LINE__);
                    return;

                }

            }

            $same_path = getSamePath($full_path, Config::$RECYCLE_BIN_PATH);
            $r_path = str_replace($same_path, '', $full_path);
            if( substr($r_path, 0, 1) == '/'){
                $r_path = substr($r_path, 1);
            }

            #PrintScreen(Config::$RECYCLE_BIN_PATH . $r_path);
            $move_to = Config::$RECYCLE_BIN_PATH . $r_path;
            $pi = pathinfo($move_to);

            Log::LogInfo('Recycle: "' . $full_path . '" --> "' . $move_to . '"');

            exec('mkdir -p -v "' . $pi['dirname'] . '"', $exec_out);
            exec('mv "' . $full_path . '" "' . $move_to . '"', $exec_out);

        } else {

            Log::LogInfo('Remove: "' . $full_path . '');
            exec('rm -r "' . $full_path . '"');

        }

    }

}

/**
 * RemoveFile
 * Löscht eine Datei bzw. verschiebt diese in den Papierkorb.
 * @param  string $filepath
 * @return void
 */
function RemoveFile($filepath){

    if(Config::$ENABLE_RECYCLE_BIN) {

        if(!CheckFilesExists(Config::$RECYCLE_BIN_PATH)) {
            
            if(!mkdir(Config::$RECYCLE_BIN_PATH)){

                Log::LogError(sprintf(LANG_FAIL_CREATE_DIRECTORY, Config::$RECYCLE_BIN_PATH), __LINE__);
                return;

            }

        }

        $pathinfo = pathinfo($filepath);
        #print_r($pathinfo);

        if(isset($path)) {

            $target_file_path = $path . $pathinfo['basename'];
            if(CheckFilesExists($target_file_path)) {
                Log::LogWarning(sprintf(LANG_FILE_EXISTS, $target_file_path), __LINE__);
            }

            if(!@rename($filepath, $target_file_path)){
                Log::LogError(sprintf(LANG_FAIL_FILE_MOVE, $filepath, $target_file_path), __LINE__);
            }

        }

    } else {

        $unlink = @unlink($filepath);
        if(!$unlink){

            Log::LogError(sprintf(LANG_FAIL_FILE_DELETE, $filepath), __LINE__);

        }

    }
}

function getDisks(){
    $found = [];
    $mnt = scandir('/mnt/');
    foreach($mnt as $disk) {

        if($disk == '.' || $disk == '..') { continue; }

        preg_match('/^cache|^disk[0-9]+|^remotes/', $disk, $output_array);
        if(count($output_array) > 0) {
            $found[] = $disk;
        }

    }

    return $found;
}

function LineQuestion(string $question, mixed $default_value = null, callable $validation = null){

    $q = '';

    if(is_bool($default_value)) {
        if($default_value) {
            $q .= ' [Y/n]';
        } else {
            $q .= ' [y/N]';
        }
    } else if (is_null($default_value)) {

    } else {
        $q .= " [default: $default_value]";
    }
    $q .= ':';

    do {

        PrintScreen($question, COLOR_BLUE, COLOR_DEFAULT, 0, false);
        PrintScreen($q, COLOR_DEFAULT, COLOR_DEFAULT, 0, false);

        $fp = fopen("php://stdin","r");
        $answer = rtrim(fgets($fp, 1024));

        if (is_bool($default_value)) {
            
            if(empty($answer)) {
                return $default_value;
            }

            if(strtolower($answer) == 'y') {
                
                return true;
            } else if(strtolower($answer) == 'n') {
                return false;
            } else {
                PrintScreen('Invalid input', COLOR_RED);
            }

        } else if (is_null($default_value)) {
            
            
            $valid = true;
            if(is_callable($validation)) { $valid = $validation($answer); }

            if($valid) {
                return $answer;
            } else {
                PrintScreen('Invalid input', COLOR_RED);
            }

        } else {
            
            if(empty($answer)) {
                return $default_value;
            }

            $valid = true;
            if(is_callable($validation)) { $valid = $validation($answer); }

            if($valid) {
                return $answer;
            } else {
                PrintScreen('Invalid input', COLOR_RED);
            }

        }
            
    } while(true);

}

function CreateTimeranges() {

    $backuptimestamps = [];
    $backuped_days = 0;
    $backuped_weeks = 0;
    $backuped_months = 0;
    $backuped_years = 0;
    $tmp_time = time();
    $backup_w = false;
    $backup_m = false;
    $backup_y = false;
    
    while (true) {

        if ($backuped_days < Config::$MAX_CONSECUTIVE_BACKUPS) {

            $backuptimestamps[] = [
                'type' => 'day',
                'start' => strtotime(date('Y-m-d 00:00:00', $tmp_time)),
                'end' => strtotime(date('Y-m-d 23:59:59', $tmp_time)),
            ];

            $backuped_days++;
            $backup_w = false;
            $backup_m = false;
            $backup_y = false;
        }

        if ($backup_w && $backuped_weeks < Config::$MAX_WEEK_BACKUPS && $backuped_days >= Config::$MAX_CONSECUTIVE_BACKUPS) {

            $backuptimestamps[] = [
                'type' => 'week',
                'start' => strtotime(date('Y-m-d 00:00:00', $tmp_time)),
                'end' => strtotime('+6 days', strtotime(date('Y-m-d 23:59:59', $tmp_time))),
            ];

            $backuped_weeks++;
            $backup_w = false;
            $backup_m = false;
            $backup_y = false;
        }

        if ($backup_m && $backuped_months < Config::$MAX_MONTH_BACKUPS && $backuped_weeks >= Config::$MAX_WEEK_BACKUPS && $backuped_days >= Config::$MAX_CONSECUTIVE_BACKUPS) {

            $start = strtotime(date('Y-m-d 00:00:00', $tmp_time));
            $end = strtotime('+1 month', $start);
            $end = strtotime('-1 day', $end);

            $backuptimestamps[] = [
                'type' => 'month',
                'start' => $start,
                'end' => strtotime(date('Y-m-d 23:59:59', $end)),
            ];

            $backuped_months++;
            $backup_m = false;
            $backup_y = false;

        }

        if ($backup_y && $backuped_years < Config::$MAX_YEAR_BACKUPS && $backuped_months >= Config::$MAX_MONTH_BACKUPS && $backuped_weeks >= Config::$MAX_WEEK_BACKUPS && $backuped_days >= Config::$MAX_CONSECUTIVE_BACKUPS) {

            $backuptimestamps[] = [
                'type' => 'year',
                'start' => strtotime(date('Y-m-d 00:00:00', $tmp_time)),
                'end' => strtotime(date('Y-12-31 23:59:59', $tmp_time)),
            ];

            $backuped_years++;
            $backup_y = false;

        }

        $tmp_time = strtotime('-1 days', $tmp_time);
        if ($tmp_time < Config::$FIRSTBACKUPTIME) {break;}

        if (date('N', $tmp_time) == 1) {
            $backup_w = true;
        }

        if (date('d', $tmp_time) == 1) {
            $backup_m = true;
        }

        if (date('m', $tmp_time) == 1 && date('d', $tmp_time) == 1) {
            $backup_y = true;
        }

    }
    
    return $backuptimestamps;
}

function createArchive($target, $log_offset = 0, Array $files = [], Array $files_string = []){

    $log_offset = str_pad('', $log_offset, ' ');

    $zip = new ZipArchive();
    $filename = $target;

    Log::LogInfo($log_offset . 'Create archive: ' . $filename);
    if($zip->open($filename, ZipArchive::CREATE) !== true) {
        Log::LogError('  Could not create');
        return false;
    }

    $filesize_decompressed = 0;
    foreach($files as $file) {
        Log::LogInfo($log_offset . '  adding File to archive: ' . $file);
        $pi = pathinfo($file);
        if(!$zip->addFile($file, $pi['basename'])){
            Log::LogError($log_offset . '    Could not add');
        } else {
            $filesize_decompressed += filesize($file);
        }
    }

    foreach($files_string as $name => $content) {
        Log::LogInfo($log_offset . '  adding String to archive: ' . $name);
        if(!$zip->addFromString($name, $content)){
            Log::LogError($log_offset . '    Could not add');
        } else {
            $filesize_decompressed += mb_strlen($content, '8bit');
        }
    }    

    $start = time();
    if(!$zip->close()) {
        Log::LogError($log_offset . '  Archive could not be finished.');
        return false;
    } else {

        Log::LogInfo($log_offset . 'Archive created');
        $time = time() - $start;
        $filesize_compressed = filesize($filename);
        $compression_ratio = number_format($filesize_decompressed / $filesize_compressed, 2);

        Log::LogInfo($log_offset . '  Worked: '. $time . ' sec.');
        Log::LogInfo($log_offset . '  Decompressed Size: ' . convertSize($filesize_decompressed));
        Log::LogInfo($log_offset . '  Compressed Size: ' . convertSize($filesize_compressed));
        Log::LogInfo($log_offset . '  Compression-Ratio: ' . $compression_ratio . 'x (' . number_format(100 - (100 / $compression_ratio), 2) . ' %)');
        
        return [
            'working_time' => $time,
            'compressed_size' => $filesize_compressed,
            'decompressed_size' => $filesize_decompressed,
            'compression_ratio' => $compression_ratio
        ];
    }

}

/**
 * Erzeugt alle Order für den angegebenen Pfad
 * @param string $path Pfad der erstellt werden soll.
 */
function createdirs($path){
    $ex = explode(DIRECTORY_SEPARATOR,$path);
    $test_path = "";
    foreach($ex as $path_tile){
        $test_path .= $path_tile . DIRECTORY_SEPARATOR;
        if(!file_exists($test_path)){
            $mkdir = mkdir($test_path);
            if($mkdir === false)
                trigger_error("Create Folder Failed",E_USER_ERROR);
        }
    }
}

if(!function_exists("readline")) {
    function readline($prompt = null){
        if($prompt){
            echo $prompt;
        }
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 1024));
        return $line;
    }
}

function convertSize($size, $multi = 1024, $round=2, $unit=array('B','kB','MB','GB','TB','PB')) {
    if(pow($multi,floor(log($size,$multi))) == 0)
        return 0;
    return round($size/pow($multi,($i=floor(log($size,$multi)))),$round).' '.$unit[$i];
}

function cmdExec($command, &$msg, &$error){
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    Log::LogDebug('Start Command: ' . $command);
    $proc = proc_open($command, $descriptorspec, $pipes);
    
    while(true) {
        $proc_details = proc_get_status($proc);
        if($proc_details['running'] !== 1) { break; }
    }
    
    $msg = '';
    while($tmp = fread($pipes[1], 10)) {
        $msg .= $tmp;
    }
    
    $error = '';
    while($tmp = fread($pipes[2], 10)) {
        $error .= $tmp;
    }

    if(mb_strlen($msg) > 0) {
        Log::LogDebug("Exec Reported Message: \r\n" . $msg);
    }
    if(mb_strlen($error) > 0) {
        Log::LogDebug("Exec Reported Error: " . $error);
    }

}

function BackupFlash(){
    
    Log::LogDebug('Flash: Start Backup');

    $zip = new ZipArchive();

    $targetpath = Config::$FLASH_BACKUP_PATH . date('Y-m-d_H.i.s') . '.zip';
    Log::LogDebug('Flash: Targetfile ' . $targetpath);

    if(!file_exists(Config::$FLASH_BACKUP_PATH)) {
        createdirs(Config::$FLASH_BACKUP_PATH);
        Log::LogInfo('Flash: Create Backuppath: ' . Config::$FLASH_BACKUP_PATH);
    }

    if(!$zip->open($targetpath, ZipArchive::CREATE)) {
        Log::LogError('Flash: Create ' . $targetpath . ' failed');
        return false;
    }

    $files = scandirRecursive('/boot');

    foreach($files as $file) {
        if(strpos($file, '/boot/.git') === false && strpos($file, '/boot/previous') === false) {

            $file_target = substr($file, 1);

            if(!$zip->addFile($file, $file_target)){
                Log::LogError('Flash: Could not add file: ' . $file);
                return false;
            }

        }
    }

    if(!$zip->close()) {
        Log::LogError('Flash: Backup failed: ' . $zip->getStatusString());
        return false;
    }
    Log::LogInfo('Flash: Backup created: ' . $zip->getStatusString());
    return true;

}

/*
function jobAdd($type, $job_type , $uuid) {
    $jobs = [];
    if(CheckFilesExists(Config::$JOB_CACHE)) {
        $jobs = json_decode(file_get_contents(Config::$JOB_CACHE), true);
    }
    $jobs[$type][] = ['uuid' => $uuid, 'job' => $job_type, 'time' => time()];
    file_put_contents(Config::$JOB_CACHE, json_encode($jobs));
}

function jobcheck($type, $job_type, $uuid){
    $jobs = [];
    if(CheckFilesExists(Config::$JOB_CACHE)) {
        $jobs = json_decode(file_get_contents(Config::$JOB_CACHE), true);
    }

    $f = array_filter($jobs[$type], function($a) use ($uuid, $job_type) { return $a['uuid'] == $uuid && $a['job'] == $job_type; });
    return count($f) > 0;
}

function jobGetTime($type, $job_type, $uuid){
    $jobs = [];
    if(CheckFilesExists(Config::$JOB_CACHE)) {
        $jobs = json_decode(file_get_contents(Config::$JOB_CACHE), true);
    }

    $f = array_filter($jobs[$type], function($a) use ($uuid, $job_type) { return $a['uuid'] == $uuid && $a['job'] == $job_type; });
    return $f[array_key_last($f)]['time'];
}

function jobremove($type, $job_type, $uuid) {
    $jobs = json_decode(file_get_contents(Config::$JOB_CACHE), true);
    $f = array_filter($jobs[$type], function($a) use ($uuid, $job_type) { return $a['uuid'] == $uuid && $a['job'] == $job_type; });
    unset($jobs[array_key_last($f)]);
    file_put_contents(Config::$JOB_CACHE, json_encode($jobs));
}
*/
?>