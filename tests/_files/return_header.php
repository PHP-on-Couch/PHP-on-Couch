<?php

if ( !function_exists('apache_request_headers')) {
    function apache_request_headers () {
        $header = array();
        foreach ( $_SERVER as $key => $value ) {
            if (substr($key,0,5) != 'HTTP_') {
                continue;
            }
            $headers[str_replace(' ', '-',ucwords(str_replace('_', ' ',strtolower(substr($key, 5)))))] = $value;
        }
        return $headers;
    }
}

echo json_encode(apache_request_headers());
