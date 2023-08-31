<?php

class JobCategory {
    const VM = 'vms';
    const CONTAINER = 'container';
    const OTHER = 'others';

    const ALL = [self::VM, self::CONTAINER, self::OTHER];
}

class Jobs {

    private static $loaded = false;
    private static $vms = [];
    private static $container = [];
    private static $others = [];
    private static $check_key = -1;

    static function add(string $category, $type, $id) {

        self::load();

        if(self::check($category, $type, $id)) { return; }
        
        if(!in_array($category, JobCategory::ALL)) { 
            Log::LogError("$category is not support as Logtype!");
            return;
        }

        self::$$category[] = [
            'id' => $id,
            'job' => $type,
            'time' => time()            
        ];

        Log::LogDebug('Add job in category "' . $category . '" with id "' . $id . '"');
        self::save();

    }

    static function check(string $category, $type, $id){
        
        self::load();

        $f = array_filter(self::$$category, function($a) use ($type, $id) {
            return $a['job'] == $type && $a['id'] == $id;
        });

        if(count($f) > 0) {
            self::$check_key = array_key_last($f);
            return true;
        }
        self::$check_key = -1;
        return false;

    }

    static function remove(string $category, $type, $id){
        
        self::load();

        if(self::check($category, $type, $id)){

            unset(self::$$category[self::$check_key]);

        }

        Log::LogDebug('Remove job from category "' . $category . '" with id "' . $id . '"');
        self::save();

    }

    static function get(string $category, $type, $id){
        
        self::load();

        if(self::check($category, $type, $id)){

            $tmp = self::$$category[self::$check_key];
            $tmp['timediff_unix'] = time() - $tmp['time'];

            $tz = date_default_timezone_get();
            date_default_timezone_set('UTC');
            $tmp['timediff_iso'] = date('H:i:s', $tmp['timediff_unix']);
            date_default_timezone_set($tz);

            return $tmp;

        }

    }

    static function getByID(string $category, $id){
        
        self::load();

        $tmp = array_filter(self::$$category, function ($a) use ($id) {
            return $a['id'] == $id;
        });

        return array_values($tmp);

    }

    static function getAll(){
        self::load();
        return [
            JobCategory::VM => self::$vms,
            JobCategory::CONTAINER => self::$container,
            JobCategory::OTHER => self::$others
        ];
    }

    private static function load() {
        
        if(self::$loaded) { return; }
        Log::LogDebug('Load jobs from "' . Config::$JOB_CACHE . '"');

        if(CheckFilesExists(Config::$JOB_CACHE)) {

            $f = file_get_contents(Config::$JOB_CACHE);
            $json = json_decode($f, true);

            self::$vms = $json[JobCategory::VM] ?? [];
            self::$container = $json[JobCategory::CONTAINER] ?? [];
            self::$others = $json[JobCategory::OTHER] ?? [];

        } else {

            self::$vms = [];
            self::$container = [];
            self::$others = [];

            self::save();
        }

        self::$loaded = true;
        
    }

    private static function save() {
        
        Log::LogDebug('Save jobfile: ' . Config::$JOB_CACHE);
        file_put_contents(Config::$JOB_CACHE, json_encode(
            [
                JobCategory::VM => self::$vms,
                JobCategory::CONTAINER => self::$container,
                JobCategory::OTHER => self::$others
            ]
        ));
    }

}

?>