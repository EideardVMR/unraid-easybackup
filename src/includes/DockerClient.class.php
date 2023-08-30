<?php

class DockerClient {

    /** @param resource */
    private $curlClient;

    /** @param string */
    private $socketPath;

    /** @param string|null */
    private $curlError = null;

    /**
     * Constructor: Initialises the Curl Resource, making it usable for subsequent
     *  API requests.
     *
     * @param string
     */
    public function __construct(string $socketPath)
    {
        $this->curlClient = curl_init();
        $this->socketPath = $socketPath;

        curl_setopt($this->curlClient, CURLOPT_UNIX_SOCKET_PATH, $socketPath);
        curl_setopt($this->curlClient, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Deconstructor: Ensure the Curl Resource is correctly closed.
     */
    public function __destruct()
    {
        curl_close($this->curlClient);
    }

    private function generateRequestUri(string $requestPath)
    {
        /* Please note that Curl doesn't use http+unix:// or any other mechanism for
         *  specifying Unix Sockets; once the CURLOPT_UNIX_SOCKET_PATH option is set,
         *  Curl will simply ignore the domain of the request. Hence why this works,
         *  despite looking as though it should attempt to connect to a host found at
         *  the domain "unixsocket". See L14 where this is set.
         *
         *  @see Client.php:L14
         *  @see https://github.com/curl/curl/issues/1338
         */
        return sprintf("http://unixsocket%s", $requestPath);
    }


    /**
     * Dispatches a command - via Curl - to Commander's Unix Socket.
     *
     * @param  string Docker Engine endpoint to hit.
     * @param  array  Data to post to $endpoint.
     * @return array  JSON decoded response from Commander.
     */
    public function dispatchCommand(string $endpoint, array $parameters = null): array | null
    {

        curl_setopt($this->curlClient, CURLOPT_URL, $this->generateRequestUri($endpoint));

        if (!is_null($parameters)) {
          $payload = json_encode($parameters);
          curl_setopt($this->curlClient, CURLOPT_POSTFIELDS, $payload);          
        }

        $result = curl_exec($this->curlClient);

        if ($result === FALSE) {
            $this->curlError = curl_error($this->curlClient);
            return array();
        }

        $result = json_decode($result, true);

        #print_r($result);

        if(is_array($result)){
            foreach($result as &$res) {
                if(isset($res['Names'])) {
                    $res['Name'] = mb_substr($res['Names'][0], 1);
                }
                
                /*
                $xml = '/boot/config/plugins/dockerMan/templates-user/my-' . substr($res['Names'][0],1) . '.xml';
                if(file_exists($xml)) {
                    $fc = file_get_contents($xml);
                    preg_match('/<Icon>(.*)<\/Icon>/', $fc, $matches);
                    if(count($matches) == 2) {
                        $res['Icon'] = $matches[1];
                    }
                }
                */
            }
        }

        return $result;
    }


    /**
     * Returns a human readable string from Curl in the event of an error.
     *
     * @return bool|string 
     */
    public function getCurlError()
    {
        return is_null($this->curlError) ? false : $this->curlError;
    }
}
?>