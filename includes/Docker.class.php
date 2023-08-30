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

    public function getStoredBackups(){
        return [];
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