<?php

// error_reporting(E_STRICT);
error_reporting(E_ALL);


class couchHttpAdapterTest extends PHPUnit_Framework_TestCase
{
    protected $client;
    protected $aclient;

    public function setUp()
    {
        $this->config = require './tests/_files/config.php';
        $client_test1 = $this->config ['databases']['client_test1'];
        $admin_config = $this->config ['databases']['client_admin'];

        $this->client = new couchClient($client_test1['uri'],$client_test1['dbname']);
        $this->aclient = new couchClient($admin_config['uri'],$admin_config['dbname']);

    }

    public function tearDown()
    {
        $this->client = null;
        $this->aclient = null;
    }

    public function testClientInitAdapter () {
        $initAdapter = $this->aclient->initAdapter(array());

        $this->assertInstanceOf('couchHttpAdapterInterface', $initAdapter);
        $this->assertInstanceOf('couchHttpAdapterAbstract', $initAdapter);

        if ( function_exists('curl_init') ) {
            $this->assertInstanceOf('couchHttpAdapterCurl', $initAdapter);
        } else {
            $this->assertInstanceOf('couchHttpAdapterSocket', $initAdapter);
        }

        $getAdapter = $this->aclient->getAdapter();
        $this->assertSame($getAdapter, $initAdapter);
    }

    public function testClientInitAdapterByGetter () {
        $adapter = $this->aclient->getAdapter();

        $this->assertInstanceOf('couchHttpAdapterInterface', $adapter);
        $this->assertInstanceOf('couchHttpAdapterAbstract', $adapter);

        if ( function_exists('curl_init') ) {
            $this->assertInstanceOf('couchHttpAdapterCurl', $adapter);
        } else {
            $this->assertInstanceOf('couchHttpAdapterSocket', $adapter);
        }

        $secondAdapter = $this->aclient->getAdapter();
        $this->assertSame($secondAdapter, $adapter);
    }

    public function testClientReInitAdapter () {
        $adapter = $this->aclient->getAdapter();

        $this->assertInstanceOf('couchHttpAdapterInterface', $adapter);
        $this->assertInstanceOf('couchHttpAdapterAbstract', $adapter);

        if ( function_exists('curl_init') ) {
            $this->assertInstanceOf('couchHttpAdapterCurl', $adapter);
        } else {
            $this->assertInstanceOf('couchHttpAdapterSocket', $adapter);
        }

        $this->aclient->initAdapter(array());
        $secondAdapter = $this->aclient->getAdapter();
        $this->assertNotSame($secondAdapter, $adapter);
    }

    public function testAdapterSendToClient () {
        if ( function_exists('curl_init') ) {
            $default = '\couchHttpAdapterCurl';
            $wanted = '\couchHttpAdapterSocket';
            $adapter = $this->getMockBuilder($wanted)
                            ->setConstructorArgs(array(array()))
                            ->getMock();
        } else {
            $default = '\couchHttpAdapterSocket';
            $wanted = '\couchHttpAdapterAbstract';
            $adapter = $this->getMockBuilder($wanted)
                            ->setConstructorArgs(array(array()))
                            ->getMockForAbstractClass();
        }

        $this->aclient->setAdapter($adapter);
        $clientAdapter = $this->aclient->getAdapter();

        $this->assertSame($adapter, $clientAdapter);
    }

    public function testAdapterGetDsn () {
        $adapter = $this->aclient->getAdapter();
        // client dsn getter must secure over client tests
        $this->assertEquals ( $adapter->getDsn(), $this->aclient->dsn() );
    }

    public function testAdapterSetDsn () {
        $adapter = $this->aclient->getAdapter();

        $dsnOld = $adapter->getDsn();
        $dsnNew = "test";

        $adapter->setDsn($dsnNew );
        $this->assertEquals ( $adapter->getDsn(), $dsnNew );
        $this->assertNotEquals ( $adapter->getDsn(), $dsnOld );
    }
}
