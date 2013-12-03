<?php

class couchHttpAdapterSocket extends couchHttpAdapterAbstract implements couchHttpAdapterInterface {
    protected $socket;

    /**
     *send a query to the CouchDB server
     *
     * In a continuous query, the server send headers, and then a JSON object per line.
     * On each line received, the $callable callback is fired, with two arguments :
     *
     * - the JSON object decoded as a PHP object
     *
     * - a couchClient instance to use to make queries inside the callback
     *
     * If the callable returns the boolean FALSE , continuous reading stops.
     *
     * @param callable $callable PHP function name / callable array ( see http://php.net/is_callable )
     * @param string $method HTTP method to use (GET, POST, ...)
     * @param string $url URL to fetch
     * @param array $parameters additionnal parameters to send with the request
     * @param string|array|object $data request body
     *
     * @return string|false server response on success, false on error
     *
     * @throws Exception|InvalidArgumentException|couchException|couchNoResponseException
     */
    public function continuousQuery($callable,$method,$url,$parameters = array(),$data = null) {
        if ( !in_array($method, $this->HTTP_METHODS )    )
            throw new Exception("Bad HTTP method: $method");
        if ( !is_callable($callable) )
            throw new InvalidArgumentException("callable argument have to success to is_callable PHP function");
        if ( is_array($parameters) AND count($parameters) )
            $url = $this->getDsn() . $url.'?'.http_build_query($parameters);
        //Send the request to the socket
        $request = $this->buildRequest($method,$url,$data,null);
        if ( !$this->_connect() )	return FALSE;
        fwrite($this->socket, $request);

        //Read the headers and check that the response is valid
        $response = '';
        $headers = false;
        while (!feof($this->socket)&& !$headers) {
            $response.=fgets($this->socket);
            if ($response == "HTTP/1.1 100 Continue\r\n\r\n") { $response = ''; continue; } //Ignore 'continue' headers, they will be followed by the real header.
            elseif (preg_match("/\r\n\r\n$/",$response) ) {
                $headers = true;
            }
        }
        $headers = explode("\n",trim($response));
        $split=explode(" ",trim(reset($headers)));
        $code = $split[1];
        unset($split);
        //If an invalid response is sent, read the rest of the response and throw an appropriate couchException
        if (!in_array($code,array(200,201))) {
            stream_set_blocking($this->socket,false);
            $response .= stream_get_contents($this->socket);
            fclose($this->socket);
            throw couchException::factory($response, $method, $url, $parameters);
        }

        //For as long as the socket is open, read lines and pass them to the callback
        $c = clone $this;
        while ($this->socket && !feof($this->socket)) {
            $e = NULL;
            $e2 = NULL;
            $read = array($this->socket);
            if (false === ($num_changed_streams = stream_select($read, $e, $e2, 1))) {
                $this->socket = null;
            } elseif ($num_changed_streams > 0) {
                $line = fgets($this->socket);
                if ( strlen(trim($line)) ) {
                    $break = call_user_func($callable,json_decode($line),$c);
                    if ( $break === FALSE ) {
                        fclose($this->socket);
                    }
                }
            }
        }
        return $code;
    }


    /**
     * build HTTP request to send to the server
     *
     * @param string $method HTTP method to use
     * @param string $url the request URL
     * @param string|object|array $data the request body. If it's an array or an object, $data is json_encode()d
     * @param string $content_type the content type of the sent data (defaults to application/json)
     * @return string HTTP request
     */
    protected function buildRequest($method,$url,$data, $content_type) {
        if ( is_object($data) OR is_array($data) )
            $data = json_encode($data);
        $req = $this->startRequestHeaders($method,$url);
        if ( $content_type ) {
            $req .= 'Content-Type: '.$content_type."\r\n";
        } else {
            $req .= 'Content-Type: application/json'."\r\n";
        }
        if ( $method == 'COPY') {
            $req .= 'Destination: '.$data."\r\n\r\n";
        } elseif ($data) {
            $req .= 'Content-Length: '.strlen($data)."\r\n\r\n";
            $req .= $data."\r\n";
        } else {
            $req .= "\r\n";
        }
        return $req;
    }


    /**
     * returns first lines of request headers
     *
     * lines :
     * <code>
     * VERB HTTP/1.0
     * Host: my.super.server.com
     * Authorization: Basic...
     * Accept: application/json,text/html,text/plain,* /*
     * </code>
     *
     * @param string $method HTTP method to use
     * @param string $url the request URL
     * @return string start of HTTP request
     */
    protected function startRequestHeaders($method,$url) {
        if ( $this->dsn_part('path') ) $url = $this->dsn_part('path').$url;
        $req = "$method $url HTTP/1.0\r\nHost: ".$this->dsn_part('host')."\r\n";
        if ( $this->dsn_part('user') && $this->dsn_part('pass') ) {
            $req .= 'Authorization: Basic '.base64_encode($this->dsn_part('user').':'.
                    $this->dsn_part('pass'))."\r\n";
        } elseif ( $this->sessioncookie ) {
            $req .= "Cookie: ".$this->sessioncookie."\r\n";
        }
        $req.="Accept: application/json,text/html,text/plain,*/*\r\n";

        return $req;
    }

	/**
	 *send a query to the CouchDB server
	 *
	 * @param string $method HTTP method to use (GET, POST, ...)
	 * @param string $url URL to fetch
	 * @param array $parameters additionnal parameters to send with the request
	 * @param string $content_type the content type of the sent data (defaults to application/json)
	 * @param string|array|object $data request body
	 *
	 * @return string|false server response on success, false on error
	 *
	 * @throws Exception
	 */
	public function query ( $method, $url, $parameters = array() , $data = NULL, $content_type = NULL ) {
	    if ( !in_array($method, $this->HTTP_METHODS )    )
	        throw new Exception("Bad HTTP method: $method");

	    if ( is_array($parameters) AND count($parameters) )
	        $url = $this->getDsn() . $url.'?'.http_build_query($parameters);

	    $request = $this->buildRequest($method,$url,$data, $content_type);
	    if ( !$this->_connect() )	return FALSE;
	    // 		echo "DEBUG: Request ------------------ \n$request\n";
	    $raw_response = $this->_execute($request);
	    $this->_disconnect();

	    // 		echo 'debug',"COUCH : Executed query $method $url";
	    // 		echo 'debug',"COUCH : ".$raw_response;
	    return $raw_response;
	}

	/**
	 * record a file located on the disk as a CouchDB attachment
	 * uses PHP socket API
	 *
	 * @param string $url CouchDB URL to store the file to
	 * @param string $file path to the on-disk file
	 * @param string $content_type attachment content_type
	 *
	 * @return string server response
	 *
	 * @throws InvalidArgumentException
	 */
	public function storeFile($url,$file,$content_type) {

	    if ( !strlen($url) )	throw new InvalidArgumentException("Attachment URL can't be empty");
	    if ( !strlen($file) OR !is_file($file) OR !is_readable($file) )	throw new InvalidArgumentException("Attachment file does not exist or is not readable");
	    if ( !strlen($content_type) ) throw new InvalidArgumentException("Attachment Content Type can't be empty");

	    $url = $this->getDsn() . $url;
	    $req = $this->startRequestHeaders('PUT',$url);
	    $req .= 'Content-Length: '.filesize($file)."\r\n"
	            .'Content-Type: '.$content_type."\r\n\r\n";
	    $fstream=fopen($file,'r');
	    $this->_connect();
	    fwrite($this->socket, $req);
	    stream_copy_to_stream($fstream,$this->socket);
	    $response = '';
	    while(!feof($this->socket))
	        $response .= fgets($this->socket);
	    $this->_disconnect();
	    fclose($fstream);
	    return $response;
	}


	/**
	 * store some data as a CouchDB attachment
	 * uses PHP socket API
	 *
	 * @param string $url CouchDB URL to store the file to
	 * @param string $data data to send as the attachment content
	 * @param string $content_type attachment content_type
	 *
	 * @return string server response
	 *
	 * @throws InvalidArgumentException
	 */
	public function storeAsFile($url,$data,$content_type) {
	    if ( !strlen($url) )	throw new InvalidArgumentException("Attachment URL can't be empty");
	    if ( !strlen($content_type) ) throw new InvalidArgumentException("Attachment Content Type can't be empty");

	    $url = $this->getDsn() . $url;
	    $req = $this->startRequestHeaders('PUT',$url);
	    $req .= 'Content-Length: '.strlen($data)."\r\n"
	            .'Content-Type: '.$content_type."\r\n\r\n";
	    $this->_connect();
	    fwrite($this->socket, $req);
	    fwrite($this->socket, $data);
	    $response = '';
	    while(!feof($this->socket))
	        $response .= fgets($this->socket);
	    $this->_disconnect();
	    return $response;
	}

	/**
	 *open the connection to the CouchDB server
	 *
	 *This function can throw an Exception if it fails
	 *
	 * @return boolean wheter the connection is successful
	 *
	 * @throws Exception
	 */
	protected function _connect() {
	    $ssl = $this->dsn_part('scheme') == 'https' ? 'ssl://' : '';
	    $this->socket = @fsockopen($ssl.$this->dsn_part('host'), $this->dsn_part('port'), $err_num, $err_string);
	    if(!$this->socket) {
	        throw new Exception('Could not open connection to '.$this->dsn_part('host').':'.$this->dsn_part('port').': '.$err_string.' ('.$err_num.')');
	    }
	    return TRUE;
	}

	/**
	 *send the HTTP request to the server and read the response
	 *
	 * @param string $request HTTP request to send
	 * @return string $response HTTP response from the CouchDB server
	 */
	protected function _execute($request) {
	    fwrite($this->socket, $request);
	    $response = '';
	    while(!feof($this->socket))
	        $response .= fgets($this->socket);
	    return $response;
	}

	/**
	 *closes the connection to the server
	 *
	 *
	 */
	protected function _disconnect() {
	    @fclose($this->socket);
	    $this->socket = NULL;
	}
}
