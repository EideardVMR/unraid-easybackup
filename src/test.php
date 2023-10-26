<?php

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$proc = proc_open('virsh snapshot-create-as --domain "Ubuntu" --name "snap0" --disk-only --quiesce --no-metadata', $descriptorspec, $pipes);
#$proc = proc_open('echo "hahaha"', $descriptorspec, $pipes);

$proc_details = proc_get_status($proc);
print_r($proc_details);

while(true) {
    $proc_details = proc_get_status($proc);
    if($proc_details['running'] !== 1) { break; }
}

echo 'Readed Message: ';
while($tmp = fread($pipes[1], 10)) {
    echo $tmp;
}
echo "\r\n";

echo 'Readed Errorline: ';
while($tmp = fread($pipes[2], 10)) {
    echo $tmp;
}
echo "\r\n";


?>