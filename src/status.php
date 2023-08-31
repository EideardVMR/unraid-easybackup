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

    $tmp['job_type'] = null;
    $tmp['job_unixtime'] = null;
    $tmp['job_timediff_unix'] = null;
    $tmp['job_timediff_iso'] = null;
    
    $jobs = Jobs::getByID(JobCategory::VM, $vm->uuid);
    foreach($jobs as $job) {

        $job = Jobs::get(JobCategory::VM, $job['job'], $job['id']);

        $tmp['job_type'] = $job['job'];
        $tmp['job_unixtime'] = $job['time'];
        $tmp['job_timediff_unix'] = $job['timediff_unix'];
        $tmp['job_timediff_iso'] = $job['timediff_iso'];

    }

    $status['vms'][] = $tmp;

}

echo json_encode($status);

exit;


?>