<?php

class couchHttpAdapterCurl extends couchHttpAdapterAbstract implements couchHttpAdapterInterface {

	/**
	* build HTTP request to send to the server
	* uses PHP cURL API
	*
	* @param string $method HTTP method to use
	* @param string $url the request URL
	* @param string|object|array $data the request body. If it's an array or an object, $data is json_encode()d
	* @param string $content_type the content type of the sent data (defaults to application/json)
	* @return resource CURL request resource
	*/
	protected function buildRequest($method,$url,$data,$content_type) {
		$http = curl_init($url);
		$http_headers = array('Accept: application/json,text/html,text/plain,*/*') ;
		if ( is_object($data) OR is_array($data) ) {
			$data = json_encode($data);
		}
		if ( $content_type ) {
			$http_headers[] = 'Content-Type: '.$content_type;
		} else {
			$http_headers[] = 'Content-Type: application/json';
		}
		if ( $this->hasSessionCookie() ) {
			$http_headers[] = "Cookie: ".$this->getSessionCookie();
		}
		curl_setopt($http, CURLOPT_CUSTOMREQUEST, $method);

		if ( $method == 'COPY') {
			$http_headers[] = "Destination: $data";
		} elseif ($data) {
			curl_setopt($http, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($http, CURLOPT_HTTPHEADER,$http_headers);
		return $http;
	}

    /*
    * add user-defined options to Curl resource
    */
    protected function addCustomOptions ($res) {
        if ( array_key_exists("curl",$this->options) && is_array($this->options["curl"]) ) {
            curl_setopt_array($res,$this->options["curl"]);
        }
    }

    /**
     *send a query to the CouchDB server
     * uses PHP cURL API
     *
     * @param string $method HTTP method to use (GET, POST, ...)
     * @param string $url URL to fetch
     * @param array $parameters additionnal parameters to send with the request
     * @param string|array|object $data request body
     * @param string $content_type the content type of the sent data (defaults to application/json)
     *
     * @return string|false server response on success, false on error
     *
     * @throws Exception
     */
    public function query ( $method, $url, $parameters = array() , $data = NULL, $content_type = NULL ) {
        if ( !in_array($method, $this->HTTP_METHODS ) ) {
            throw new Exception("Bad HTTP method: $method");
        }

        $url = $this->getDsn().$url;
        if ( is_array($parameters) AND count($parameters) ) {
            $url = $url.'?'.http_build_query($parameters);
        }

        $http = $this->buildRequest($method,$url,$data, $content_type);
        $this->addCustomOptions ($http);
        curl_setopt($http,CURLOPT_HEADER, true);
        curl_setopt($http,CURLOPT_RETURNTRANSFER, true);
        if ($this->curlAllowFollowLocation()) {
            curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);
        }

        $response = curl_exec($http);
        curl_close($http);

        return $response;
    }
    /**
    * store some data as a CouchDB attachment
    * uses PHP cURL API
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
        if ( !strlen($url) ) {
            throw new InvalidArgumentException("Attachment URL can't be empty");
        }
        if ( !strlen($content_type) ) {
            throw new InvalidArgumentException("Attachment Content Type can't be empty");
        }
        $url = $this->dsn.$url;
        $http = curl_init($url);
        $http_headers = array(
            'Accept: application/json,text/html,text/plain,*/*',
            'Content-Type: '.$content_type,
            'Expect: ',
            'Content-Length: '.strlen($data)
        ) ;
        if ( $this->hasSessionCookie() ) {
            $http_headers[] = "Cookie: ".$this->getSessionCookie();
        }
        curl_setopt($http, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($http, CURLOPT_HTTPHEADER,$http_headers);
        curl_setopt($http, CURLOPT_HEADER, true);
        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        if ($this->curlAllowFollowLocation()) {
            curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);
        }
        curl_setopt($http, CURLOPT_POSTFIELDS, $data);
        $this->addCustomOptions ($http);
        $response = curl_exec($http);
        curl_close($http);
        return $response;
    }

    /**
    * record a file located on the disk as a CouchDB attachment
    * uses PHP cURL API
    *
    * @param string $url CouchDB URL to store the file to
    * @param string $file path to the on-disk file
    * @param string $content_type attachment content_type
    *
    * @return string server response
    *
    * @throws InvalidArgumentException
    */
    public function storeFile ( $url, $file, $content_type ) {
        if ( !strlen($url) ) {
            throw new InvalidArgumentException("Attachment URL can't be empty");
        }
        if ( !strlen($file) OR !is_file($file) OR !is_readable($file) ) {
            throw new InvalidArgumentException("Attachment file does not exist or is not readable");
        }

        if ( !strlen($content_type) ) {
            throw new InvalidArgumentException("Attachment Content Type can't be empty");
        }
        $url = $this->dsn.$url;
        $http = curl_init($url);
        $http_headers = array(
            'Accept: application/json,text/html,text/plain,*/*',
            'Content-Type: '.$content_type,
            'Expect: '
        );
        if ( $this->hasSessionCookie() ) {
            $http_headers[] = "Cookie: ".$this->getSessionCookie();
        }
        curl_setopt($http, CURLOPT_PUT, 1);
        curl_setopt($http, CURLOPT_HTTPHEADER,$http_headers);
        curl_setopt($http, CURLOPT_UPLOAD, true);
        curl_setopt($http, CURLOPT_HEADER, true);
        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        if ($this->curlAllowFollowLocation()) {
            curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);
        }
        $fstream=fopen($file,'r');
        curl_setopt($http, CURLOPT_INFILE, $fstream);
        curl_setopt($http, CURLOPT_INFILESIZE, filesize($file));
        $this->addCustomOptions ($http);
        $response = curl_exec($http);
        fclose($fstream);
        curl_close($http);
        return $response;
    }


    public function continuousQuery($callable,$method,$url,$parameters = array(),$data = null) {
        static $socketAdapter;

        if ($socketAdapter == null) {
            $socketAdapter = new couchHttpAdapterSocket($this->getOptions());
        }

        $socketAdapter->continuousQuery($callable, $method, $url,$parameters,$data);
    }

    protected function curlAllowFollowLocation() {
        return ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off');
    }
}