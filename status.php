<?php

require_once '/usr/local/emhttp/plugins/smbackup/includes/loader.php';

$kvm = new KVM();
$vms = $kvm->getVMs();

$jobs = Jobs::getAll();
$status = [];

foreach($vms as $vm) {
    $tmp = [];

    $tmp['id'] = $vm->uuid;
    $tmp['name'] = $vm->name;
    $tmp['state'] = $vm->state->value;
    
    $f = array_filter($jobs[JobCategory::VM], function($a) use ($tmp) { return $a['id'] ==$tmp['id']; });

    if(count($f) > 0) {
        $f = $f[array_key_last($f)];
        $tmp['job_type'] = $f['job'];
        $tmp['job_unixtime'] = $f['time'];
        $tmp['job_timediff_unix'] = $f['timediff_unix'];
        $tmp['job_timediff_iso'] = $f['timediff_iso'];
    } else {
        $tmp['job_type'] = null;
        $tmp['job_unixtime'] = null;
        $tmp['job_timediff_unix'] = null;
        $tmp['job_timediff_iso'] = null;
    }

    $status['vms'][] = $tmp;

}

echo json_encode($status);

exit;


?>