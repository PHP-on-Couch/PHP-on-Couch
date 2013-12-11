<?php

abstract class couchHttpAdapterAbstract implements couchHttpAdapterInterface {
    protected $dsn = null;
    protected $options = null;
    /**
    * @var array allowed HTTP methods for REST dialog
    */
    protected $HTTP_METHODS = array(SELF::METHODE_PUT,SELF::METHODE_POST,SELF::METHODE_GET,SELF::METHODE_DELETE,SELF::METHODE_COPY);

	/**
	* @var string the session cookie
	*/
	protected $sessioncookie = null;

    public function setDsn ($dsn) {
        $this->dsn= $dsn;
    }
    public function getDsn () {
        return $this->dsn;
    }

    public function setOptions ($options) {
        $this->options = $options;
    }

    public function getOptions () {
        return $this->options;
    }

    public function __construct($options) {
        $this->setOptions($options);
    }

	/**
	* set the session cookie to send in the headers
	* @param string $cookie the session cookie ( example : AuthSession=Y291Y2g6NENGNDgzNz )
	*
	* @return \couch
	*/
	public function setSessionCookie ( $cookie ) {
		$this->sessioncookie = $cookie;
		return $this;
	}

    /**
     * get the session cookie
     *
     * @return string cookie
     */
    public function getSessionCookie () {
        return $this->sessioncookie;
    }

    /**
     * get the session cookie
     *
     * @return string cookie
     */
    public function hasSessionCookie () {
        return (bool) $this->sessioncookie;
    }

    abstract public function query ( $method, $url, $parameters = array() , $data = NULL, $content_type = NULL );
    abstract public function storeAsFile($url,$data,$content_type);
    abstract public function storeFile($url,$file,$content_type);
    abstract public function continuousQuery($callable,$method,$url,$parameters = array(),$data = null);
}
