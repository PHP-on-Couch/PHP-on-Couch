<?php

interface couchHttpAdapterInterface {
    const METHODE_PUT    = 'PUT';
    const METHODE_POST   = 'POST';
    const METHODE_GET    = 'GET';
    const METHODE_DELETE = 'DELETE';
    const METHODE_COPY   = 'COPY';


    public function setDsn($dsn);
    public function getDsn();
    public function setOptions($options);
    public function getOptions();
    public function query ( $method, $url, $parameters = array() , $data = NULL, $content_type = NULL );
    public function storeAsFile($url,$data,$content_type);
    public function storeFile($url,$file,$content_type);
    public function continuousQuery($callable,$method,$url,$parameters = array(),$data = null);

    public function setSessionCookie( $cookie );
    public function getSessionCookie();
}
