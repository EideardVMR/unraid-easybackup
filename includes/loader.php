<?php

#echo "Debugmark L1<br>";
$_pluginpath = '/usr/local/emhttp/plugins/smbackup/';
#echo "Debugmark L2<br>";
require_once $_pluginpath . 'includes/Log.class.php';
#echo "Debugmark L3<br>";
require_once $_pluginpath . 'includes/errorhandler.php';
#echo "Debugmark L4<br>";
require_once $_pluginpath . 'includes/Language.class.php';
#echo "Debugmark L5<br>";
Language::LoadLanguage('en');
#echo "Debugmark L6<br>";
require_once $_pluginpath . 'includes/functions.php';
#echo "Debugmark L7<br>";
require_once $_pluginpath . 'includes/config.php';
#echo "Debugmark L8<br>";
Config::Load();
#echo "Debugmark L9<br>";
require_once $_pluginpath . 'includes/Jobs.class.php';
#echo "Debugmark L10<br>";
require_once $_pluginpath . 'includes/KVM.class.php';
#echo "Debugmark L11<br>";
require_once $_pluginpath . 'includes/DockerClient.class.php';
#echo "Debugmark L12<br>";
require_once $_pluginpath . 'includes/Docker.class.php';
#echo "Debugmark L13<br>";
?>