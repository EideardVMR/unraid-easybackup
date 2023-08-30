<?php

class Language {

    static function LoadLanguage($lang){

        if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'lang_' . $lang . '.php')) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'lang_' . $lang . '.php';
        } else {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'lang_en.php';
        }

    }

}

?>