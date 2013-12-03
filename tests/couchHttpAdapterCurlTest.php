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
}
