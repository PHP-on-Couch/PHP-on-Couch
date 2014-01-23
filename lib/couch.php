<?PHP
/*
Copyright (C) 2009  Mickael Bailly

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
* couch class
*
* basics to implement JSON / REST / HTTP CouchDB protocol
*
*/
class couch {
	/**
	* @var string database source name
	*/
	protected $dsn = '';

	/**
	* @var array database source name parsed
	*/
	protected $dsn_parsed = null;

	/**
	* @var array couch options
	*/
	protected $options = null;

	/**
	 *
	 * @var couchHttpAdapterInterface
	 */
	protected $adapter = null;


	/**
	* class constructor
	*
	* @param string $dsn CouchDB Data Source Name
	*	@param array $options Couch options
	*/
	public function __construct ($dsn, $options = array() ) {
		$this->dsn = preg_replace('@/+$@','',$dsn);
		$this->options = $options;
		$this->dsn_parsed = parse_url($this->dsn);
		if ( !isset($this->dsn_parsed['port']) ) {
			$this->dsn_parsed['port'] = 80;
		}
	}

	/**
	 *
	 * @return couchHttpAdapterInterface
	 */
	public function getAdapter() {
	    if ( $this->adapter === null ) {
            $this->adapter = $this->initAdapter($this->options);
	    }

	    return $this->adapter;
	}
	/**
	 *
	 * @param couchHttpAdapterInterface $adapter
	 */
	public function setAdapter( couchHttpAdapterInterface $adapter) {
		$this->adapter = $adapter;
	}

	/**
	 *
	 * @return couchHttpAdapterInterface
	 */
	public function initAdapter($options) {
	    if ( $options === null ) {
	        $options = $this->options;
	    }

	    if ( function_exists('curl_init') ) {
	        $adapter = new couchHttpAdapterCurl($options);
	    } else {
	        $adapter = new couchHttpAdapterSocketCurl($options);
	    }
	    $adapter->setDsn($this->dsn());

	    $this->adapter = $adapter;
		return $adapter;
	}


	/**
	* returns the DSN, untouched
	*
	* @return string DSN
	*/
	public function dsn() {
		return $this->dsn;
	}

	/**
	* returns the options array
	*
	* @return string DSN
	*/
	public function options() {
		return $this->options;
	}

	/**
	* get the session cookie
	*
	* @return string cookie
	*/
	public function getSessionCookie () {
		return $this->getAdapter()->getSessionCookie();
	}

	/**
	* set the session cookie to send in the headers
	* @param string $cookie the session cookie ( example : AuthSession=Y291Y2g6NENGNDgzNz )
	*
	* @return \couch
	*/
	public function setSessionCookie ( $cookie ) {
		return $this->getAdapter()->setSessionCookie($cookie);
	}


	/**
	* return a part of the data source name
	*
	* if $part parameter is empty, returns dns array
	*
	* @param string $part part to return
	* @return string DSN part
	*/
	public function dsn_part($part = null) {
		if ( !$part ) {
			return $this->dsn_parsed;
		}
		if ( isset($this->dsn_parsed[$part]) ) {
			return $this->dsn_parsed[$part];
		}
	}

	/**
	* parse a CouchDB server response and sends back an array
	* the array contains keys :
	* status_code : the HTTP status code returned by the server
	* status_message : the HTTP message related to the status code
	* body : the response body (if any). If CouchDB server response Content-Type is application/json
	*        the body will by json_decode()d
	*
	* @todo support follow url header
	*
	* @static
	* @param string $raw_data data sent back by the server
	* @param boolean $json_as_array is true, the json response will be decoded as an array. Is false, it's decoded as an object
	* @return array CouchDB response
	* @throws InvalidArgumentException
	*/
	public static function parseRawResponse($raw_data, $json_as_array = FALSE) {
		if ( !strlen($raw_data) ) throw new InvalidArgumentException("no data to parse");
		while ( !substr_compare($raw_data, "HTTP/1.1 100 Continue\r\n\r\n", 0, 25) ) {
			$raw_data = substr($raw_data, 25);
		}
		$response = array('body'=>null);
		list($headers, $body) = explode("\r\n\r\n", $raw_data,2);
		$headers_array=explode("\n",$headers);
		$status_line = reset($headers_array);
		$status_array = explode(' ',$status_line,3);
		$response['status_code'] = trim($status_array[1]);
		$response['status_message'] = trim($status_array[2]);
		if ( strlen($body) ) {
			$response['body'] = preg_match('@Content-Type:\s+application/json@i',$headers) ? json_decode($body,$json_as_array) : $body ;
		}
		return $response;
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
	*/
	public function query ( $method, $url, $parameters = array() , $data = NULL, $content_type = NULL ) {
	    return $this->getAdapter()->query($method,$url,$parameters, $data, $content_type);
	}

	/**
	* record a file located on the disk as a CouchDB attachment
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $file path to the on-disk file
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	public function storeFile ( $url, $file, $content_type ) {
	    return $this->getAdapter()->storeFile($url,$file,$content_type);
	}

	/**
	* store some data as a CouchDB attachment
	*
	* @param string $url CouchDB URL to store the file to
	* @param string $data data to send as the attachment content
	* @param string $content_type attachment content_type
	*
	* @return string server response
	*/
	public function storeAsFile($url,$data,$content_type) {
	    return $this->getAdapter()->storeAsFile($url,$data,$content_type);
	}

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
	    return $this->getAdapter()->continuousQuery($callable,$method,$url,$parameters,$data);
	}
}
