<?php

namespace Blackxel\FacturaPyme;

use Exception;

class SDK
{
    protected $host;
    protected $headers = array(
        'User-Agent' =>  'SDK FacturaPyme',
        'Cache-Control' => 'no-cache'
    );
    public $ignoreSSL = false;
    public $debug = false;
    protected $trace = '';
    protected $curl;
    public function __construct($host, $apiKey, $version = 'v1')
    {
        $host = str_replace('/api', '', rtrim($host, '/'));
        $this->host = $host.'/api/'.$version.'/';
        $this->headers['Authorization'] = $apiKey;
    }
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }
    public function getTrace()
    {
        return $this->trace;
    }
    public function pdf($tipoDte, $folio)
    {
        return $this->exec('dte/pdf/'.$tipoDte.'/'.$folio);
    }
    public function xml($tipoDte, $folio)
    {
        return $this->exec('dte/xml/'.$tipoDte.'/'.$folio);
    }
    public function exec($endPoint, $data = null, $method = 'GET')
    {
        $verbose = null;
        $this->trace = '';
        $method = strtoupper($method);
        if (!$this->curl = curl_init()) {
            throw new Exception("Couldn't initialize a cURL handle");
        }
        $options = $this->setOptions($endPoint, $data, $method);
        if ($this->debug) {
            $options[CURLOPT_VERBOSE] = true;
            $verbose = fopen('php://temp', 'w+');
            $options[CURLOPT_STDERR] = $verbose;
        }
        curl_setopt_array($this->curl, $options);
        $response = curl_exec($this->curl);
        if ($this->debug) {
            rewind($verbose);
            $this->trace = stream_get_contents($verbose);
            fclose($verbose);
        }
        return $this->processResponse($response);
    }
    protected function processResponse($response)
    {
        $errorCode = curl_errno($this->curl);
        if ($errorCode) {
            $errorText = curl_error($this->curl);
            curl_close($this->curl);
            throw new Exception("cUrl error: ".$errorText, $errorCode);
        }
        $info = curl_getinfo($this->curl);
        curl_close($this->curl);
        if ($info['content_type'] == 'application/json') {
            $response = json_decode($response);
        }
        if ($info['http_code'] > 400) {
            $errorText = $response;
            if (is_object($errorText)) {
                $errorText = $errorText->message;
            }
            throw new Exception("error: ".$errorText, $info['http_code']);
        }
        return $response;
    }
    protected function setOptions($endPoint, $data = null, $method = 'GET')
    {
        $options = array(
            CURLOPT_HTTPHEADER     => $this->normalizeHeader(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_URL            => $this->host.ltrim($endPoint, '/'),
        );
        if ($this->ignoreSSL) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
        }
        if ($data) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $data;
        }
        if ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }
        return $options;
    }
    protected function normalizeHeader()
    {
        $headers = array();
        foreach ($this->headers as $name => $value) {
            $headers[] = $name .': '.$value;
        }
        return $headers;
    }
}
