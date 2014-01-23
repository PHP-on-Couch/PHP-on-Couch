<?php

header('Content-Type: application/json');

if (!function_exists('apache_request_headers')) {
    function myUcfirst(&$item)
    {
        $item= ucwords(strtolower($item));
    }

    function apache_request_headers ()
    {
        $header = array();

        $headers['foo'] = "TEST";
        foreach ($_SERVER as $key => $value) {

            $keyParts = explode('_', $key);
            if ('HTTP' != array_shift($keyParts)) {
                continue;
            }

            array_walk($keyParts, 'myUcfirst');
            $headers[implode('-', $keyParts)] = $value;
        }
        return $headers;
    }
}

echo json_encode(apache_request_headers());
