<?php

// error_reporting(E_STRICT);
error_reporting(E_ALL);

class couchHttpAdapterCurlTest extends PHPUnit_Framework_TestCase
{

    protected $adapter;

    public function setUp()
    {
        $this->config = require './tests/_files/config.php';
        $admin_config = $this->config['databases']['client_admin'];

        $this->adapter = new couchHttpAdapterCurl(array());
        $this->adapter->setDsn($admin_config['uri']);
    }

    public function tearDown()
    {
        $this->adapter = null;
    }

    public function testAdapterSendToClient()
    {
        $adapter = $this->adapter;

        $id = 'testid';
        $data = array(
            'foo' => 'bar'
        );
        $response = $adapter->query($adapter::METHODE_PUT, '/' . $id, array(), $data);
    }

    public function testCustomCurlOptions()
    {
        $adapter = $this->adapter;
        $adapterOptions = array(
            'curl' => array(
                CURLOPT_URL => 'http://www.example.com/'
            )
        );

        $addCustomOptions = new \ReflectionMethod($adapter, 'addCustomOptions');
        $addCustomOptions->setAccessible(true);

        $curlHandle = curl_init();

        $adapter->setOptions($adapterOptions);
        $addCustomOptions->invoke($adapter, $curlHandle);

        $info = curl_getinfo($curlHandle);
        $this->assertEquals($adapterOptions['curl'][CURLOPT_URL], $info['url']);
    }

    public function testBuildRequestSendCookie()
    {
        $sessionCookie = "foo=bar";

        $adapter = $this->adapter;
        $adapter->setSessionCookie($sessionCookie);

        $this->assertTrue($adapter->hasSessionCookie());
        $this->assertEquals($sessionCookie, $adapter->getSessionCookie());

        $buildRequest = new \ReflectionMethod($adapter, 'buildRequest');
        $buildRequest->setAccessible(true);

        $curlHandle = $buildRequest->invokeArgs($adapter, array(
            'COPY',
            'localhost:8080/_files/return_header.php',
            array(
                'foo' => 'bar'
            ),
            null
        ));

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $header = json_decode(curl_exec($curlHandle), true);

        $this->assertArrayHasKey('Cookie', $header);
        $this->assertEquals($sessionCookie, $header['Cookie']);
        $this->assertEquals($adapter->getSessionCookie(), $header['Cookie']);
    }

    public function testBuildRequestSendCustomContentType()
    {
        $contentType = "foo/bar";

        $adapter = $this->adapter;

        $buildRequest = new \ReflectionMethod($adapter, 'buildRequest');
        $buildRequest->setAccessible(true);

        $curlHandle = $buildRequest->invokeArgs($adapter, array(
            'COPY',
            'localhost:8080/_files/return_header.php',
            array(
                'foo' => 'bar'
            ),
            $contentType
        ));

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $header = json_decode(curl_exec($curlHandle), true);

        $this->assertArrayHasKey('Content-Type', $header);
        $this->assertEquals($contentType, $header['Content-Type']);
    }

    public function testBuildRequestSendDefaultContentType()
    {
        $defaultContentType = "application/json";

        $adapter = $this->adapter;

        $buildRequest = new \ReflectionMethod($adapter, 'buildRequest');
        $buildRequest->setAccessible(true);

        $curlHandle = $buildRequest->invokeArgs($adapter, array(
            'COPY',
            'localhost:8080/_files/return_header.php',
            array(
                'foo' => 'bar'
            ),
            null
        ));

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $header = json_decode(curl_exec($curlHandle), true);

        $this->assertArrayHasKey('Content-Type', $header);
        $this->assertEquals($defaultContentType, $header['Content-Type']);
    }

    public function testQueryNotSupportedMethod()
    {
        $method = 'NO_SUPPORTED_METHODE';
        $this->setExpectedException('Exception', sprintf("Bad HTTP method: %s", $method));

        $adapter = $this->adapter;

        $adapter->query($method, '/dev/null');
    }

    public function testQuerySendParameters()
    {
        $abstact = $this->adapter;

        $abstact->setDsn('http://localhost:8080/');

        $query = array(
            'foo' => 'bar',
            'bar' => 'baz'
        );

        $response = $abstact->query($abstact::METHODE_GET, '_files/return_url_parts.php', $query);
        $response = couch::parseRawResponse($response, true);

        $this->assertEquals('foo=bar&bar=baz', $response['body']['query']);
    }

    /**
     * PHP-on-Couch/PHP-on-Couch/issues/12
     */
    public function testQueryWorkWithRedirect()
    {
        // $this->markTestIncomplete('parse response currently not support follow url');

        /* @var $adapter \couchHttpAdapterCurl */
        $adapter = $this->getMockBuilder('\couchHttpAdapterCurl')
            ->setConstructorArgs(array(
            $this->adapter->getOptions()
        ))
            ->setMethods(array(
            'curlAllowFollowLocation'
        ))
            ->getMock();

        $adapter->expects($this->any())
            ->method('curlAllowFollowLocation')
            ->will($this->returnValue(true));

        $this->assertInstanceOf('couchHttpAdapterCurl', $adapter);
        $adapter->setDsn('http://localhost:8080/');

        $query = 'bar=baz&foo=bar';
        $queryToSend = array(
            'redirect' => 'http://localhost:8080/_files/return_url_parts.php?' . $query
        );

        $response = $adapter->query(couchHttpAdapterCurl::METHODE_POST, '_files/redirect_to_url.php', $queryToSend);
        $urlChar = '?.&';

        $response = explode("\r\n\r\n", $response);

        $this->assertRegExp('#HTTP/[0-9.]+ 30. .+#', $response[0]);
        $this->assertRegExp(sprintf('#Location: %s#', addcslashes($queryToSend['redirect'], $urlChar)), $response[0]);

        $this->assertRegExp('#HTTP/[0-9.]+ 200 OK#', $response[1]);
        $this->assertRegExp('#Content-Type: application/json#', $response[1]);

        $body = json_decode($response[2], true);

        $this->assertEquals($query, $body['query']);
    }

    public function testStoreAsFileWithoutUrl()
    {
        $this->setExpectedException('\InvalidArgumentException', "Attachment URL can't be empty");

        $this->adapter->storeAsFile('', '', '');
    }

    public function testStoreAsFileWithoutContentType()
    {
        $this->setExpectedException('\InvalidArgumentException', "Attachment Content Type can't be empty");

        $this->adapter->storeAsFile('http://localhost:8080/', '', '');
    }

    public function testStoreAsFile()
    {

        $data = array(
            'foo'=>'bar',
            'bar'=>'baz',
        );

        $this->adapter->setDsn('http://localhost:8080/');

        $response = $this->adapter->storeAsFile(
            '/_files/return_header_and_body.php',
            json_encode($data),
            'javascript/json'
        );
        $response = couch::parseRawResponse($response, true);

        $this->assertEquals(json_encode($data), $response['body']['body']);

    }

    public function testStoreAsFileWithCookie()
    {

        $data = array(
            'foo'=>'bar',
            'bar'=>'baz',
        );

        $cookie = 'secret=foobar';

        $this->adapter->setDsn('http://localhost:8080/');
        $this->adapter->setSessionCookie($cookie);

        $response = $this->adapter->storeAsFile(
            '/_files/return_header.php',
            json_encode($data),
            'javascript/json'
        );
        $response = couch::parseRawResponse($response, true);

        $this->assertArrayHasKey('Cookie', $response['body']);
        $this->assertEquals($cookie, $response['body']['Cookie']);

    }

    public function testStoreAsFileWithFollowLocation()
    {
        $data = array(
            'foo'=>'bar',
            'bar'=>'baz',
        );

        $this->adapter->setDsn('http://localhost:8080/');
        $query = array('redirect'=>'http://localhost:8080/_files/return_header_and_body.php');

        $response = $this->adapter->storeAsFile(
            '/_files/redirect_to_url.php?' . http_build_query($query),
            json_encode($data),
            'javascript/json'
        );

        echo "\n\n";
        $response = explode("\r\n\r\n", $response);
        $responseBody = json_decode($response[2], true);
//         $response = couch::parseRawResponse($response, true);

        $this->assertArrayHasKey('body', $responseBody);
        $this->assertEquals(json_encode($data), $responseBody['body']);

    }

    public function testStoreFileWithoutUrl()
    {
        $this->setExpectedException('\InvalidArgumentException', "Attachment URL can't be empty");

        $this->adapter->storeFile('', '', '');
    }

    public function testStoreFileWithoutContentType()
    {
        $this->setExpectedException('\InvalidArgumentException', "Attachment Content Type can't be empty");

        $this->adapter->storeFile('http://localhost:8080/', __FILE__, '');
    }

    public function testStoreFileWithFileLikeNull()
    {
        $this->setExpectedException('\InvalidArgumentException', "Attachment file does not exist or is not readable");

        $this->adapter->storeFile('http://localhost:8080/', null, '');
    }

    public function testStoreFileWithFileLikeEmtpyString()
    {
        $this->setExpectedException('\InvalidArgumentException', "Attachment file does not exist or is not readable");

        $this->adapter->storeFile('http://localhost:8080/', '', '');
    }

    public function testStoreFileWithFileUnreadable()
    {
        $this->markTestIncomplete();

        $this->setExpectedException('\InvalidArgumentException', "Attachment file does not exist or is not readable");
        $this->adapter->storeFile('http://localhost:8080/', '/', '');
    }

    public function testStoreFile()
    {
        $cookie = 'secret=foobar';

        $this->adapter->setSessionCookie($cookie);

        $this->adapter->storeFile('http://localhost:8080/', __FILE__, 'text/plain');

    }

    public function testInitSocketAdapter()
    {
        $initSocketAdapter = new ReflectionMethod($this->adapter, 'initSocketAdapter');
        $initSocketAdapter->setAccessible(true);

        $socketAdapter = new ReflectionProperty($this->adapter, 'socketAdapter');
        $socketAdapter->setAccessible(true);

        $initSocketAdapter->invoke($this->adapter);

        /* @var $socketAdapterCurrent \couchHttpAdapterSocket */
        $socketAdapterCurrent = $socketAdapter->getValue($this->adapter);

        $this->assertInstanceOf('\couchHttpAdapterSocket', $socketAdapterCurrent);
        $this->assertEquals($this->adapter->getOptions(), $socketAdapterCurrent->getOptions());
    }

    public function testContinuousQuery()
    {
        $adapter = $this->getMockBuilder(get_class($this->adapter))
                        ->setMethods(array('initSocketAdapter'))
                        ->setConstructorArgs(array($this->adapter->getOptions()))
                        ->getMock();

        $mockedSocketAdapter = $this->getMockBuilder('\couchHttpAdapterSocket')
                                    ->setMethods(array('continuousQuery'))
                                    ->setConstructorArgs(array($this->adapter->getOptions()))
                                    ->getMock();

        $socketAdapter = new ReflectionProperty($adapter, 'socketAdapter');
        $socketAdapter->setAccessible(true);

        $adapter->expects($this->any())
                ->method('initSocketAdapter')
                ->will($this->returnCallback(function () use (&$socketAdapter, &$adapter, &$mockedSocketAdapter) {
                    $socketAdapter->setValue($adapter, $mockedSocketAdapter);
                }));

        $mockedSocketAdapter->expects($this->any())
                            ->method('continuousQuery')
                            ->will($this->returnCallback(function () {
                                return func_get_args();
                            }));

        /* setup end */
        $return = $adapter->continuousQuery('callable', 'http_methode', 'url', 'parameters', 'data');


        $this->assertInternalType('array', $return);
        $this->assertCount(5, $return);
        $this->assertEquals('callable', $return[0]);
        $this->assertEquals('http_methode', $return[1]);
        $this->assertEquals('url', $return[2]);
        $this->assertEquals('parameters', $return[3]);
        $this->assertEquals('data', $return[4]);
    }
}
