<?php

require_once '/usr/local/emhttp/plugins/easybackup/includes/loader.php';

Log::$logging = false;

$kvm = new KVM();
$vms = $kvm->getVMs();

Jobs::deleteAbortedJobs(true);

$jobs = Jobs::getAll();
$status = [];

foreach($vms as $vm) {
    $tmp = [];

    $tmp['id'] = $vm->uuid;
    $tmp['name'] = $vm->name;
    $tmp['state'] = $vm->state->value;
    $tmp['guestagent'] = $vm->checkGuestAgent(); 

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

$docker = new Docker();
$containers = $docker->getContainers();

foreach($containers as $container) {

    $tmp = [];

    $tmp['id'] = $container->id;
    $tmp['name'] = $container->name;
    $tmp['state'] = $container->state;

    $tmp['job_type'] = null;
    $tmp['job_unixtime'] = null;
    $tmp['job_timediff_unix'] = null;
    $tmp['job_timediff_iso'] = null;
    
    $jobs = Jobs::getByID(JobCategory::CONTAINER, $container->id);
    foreach($jobs as $job) {

        $job = Jobs::get(JobCategory::CONTAINER, $job['job'], $job['id']);

        $tmp['job_type'] = $job['job'];
        $tmp['job_unixtime'] = $job['time'];
        $tmp['job_timediff_unix'] = $job['timediff_unix'];
        $tmp['job_timediff_iso'] = $job['timediff_iso'];

    }

    $status['container'][] = $tmp;

}

echo json_encode($status);

exit;


?>