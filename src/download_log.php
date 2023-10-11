<?php

    if(file_exists('/boot/config/plugins/easybackup/easybackup.log')) {
        $data = file_get_contents('/boot/config/plugins/easybackup/easybackup.log');
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary"); 
        header("Content-disposition: attachment; filename=\"" . basename($data) . "\""); 
        echo $data;
        exit;
    }

    echo "File not found!";

    exit;

?>