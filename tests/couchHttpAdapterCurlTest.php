<?php

// error_reporting(E_STRICT);
error_reporting(E_ALL);


class couchHttpAdapterCurlTest extends PHPUnit_Framework_TestCase
{
    protected $adapter;

    public function setUp()
    {
        $this->config = require './tests/_files/config.php';
        $admin_config = $this->config ['databases']['client_admin'];

        $this->adapter = new couchHttpAdapterCurl(array());
        $this->adapter->setDsn($admin_config['uri']);
    }

    public function tearDown()
    {
        $this->adapter = null;
    }

    public function testAdapterSendToClient () {
        $adapter = $this->adapter;

        $id = 'testid';
        $data = array(
            'foo'=>'bar',
        );
        $response = $adapter->query($adapter::METHODE_PUT,$id,array(),$data);
    }

    public function testCustomCurlOptions () {
        $adapter = $this->adapter;
        $adapterOptions=array('curl'=>array(CURLOPT_URL=>'http://www.example.com/'));

        $addCustomOptions = new \ReflectionMethod($adapter, 'addCustomOptions');
        $addCustomOptions->setAccessible(true);

        $curlHandle= curl_init();

        $adapter->setOptions($adapterOptions);
        $addCustomOptions->invoke($adapter,$curlHandle);

        $info = curl_getinfo($curlHandle);
        $this->assertEquals($adapterOptions['curl'][CURLOPT_URL],$info['url']);

    }

    public function testBuildRequestSendCookie () {
        $sessionCookie = "foo=bar";


        $adapter = $this->adapter;
        $adapter->setSessionCookie($sessionCookie);

        $buildRequest = new \ReflectionMethod($adapter, 'buildRequest');
        $buildRequest->setAccessible(true);

        $curlHandle = $buildRequest->invokeArgs($adapter,array(
            'COPY',
            'localhost:8080/_files/return_header.php',
            array('foo'=>'bar'),
            null
        ));

        $header= json_decode(curl_exec($curlHandle));

        $this->assertArrayHasKey('Cookie', $header);
        $this->assertEquals($sessionCookie, $header['Cookie']);
        $this->assertEquals($adapter->getSessionCookie(), $header['Cookie']);
    }
}
