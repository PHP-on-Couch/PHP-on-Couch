<?php

function tryStartBuildInServer ()
{
    if (!defined('WEB_SERVER_HOST')) {
        $phpunitXmlFiles = array(
            './tests/phpunit.xml',
            './tests/phpunit.xml.dist',
        );
        foreach ($phpunitXmlFiles as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }
            $phpunitXml = new SimpleXMLElement(file_get_contents($filePath));

            if (isset($phpunitXml->php->const)) {
                foreach ($phpunitXml->php->const as $const) {
                    $const = (array) $const;

                    define($const['@attributes']['name'], $const['@attributes']['value']);
                }
            }


            break;
        }
    }

    if (defined('WEB_SERVER_HOST')) {
        $buildInServer = array(
            'host'=>WEB_SERVER_HOST,
            'port'=>WEB_SERVER_PORT,
            'docRoot'=>WEB_SERVER_DOCROOT
        );
    }

    if (isset($buildInServer)) {
        startBuildInServer($buildInServer);
    } else {
        echo "PHP build-in Server can't startet!" . PHP_EOL . PHP_EOL;
    }
}
function startBuildInServer ($buildInServer)
{
    extract($buildInServer);

    // Command that starts the built-in web server
    $command = sprintf(
        'php -S %s:%d -t %s >/dev/null 2>&1 & echo $!',
        $host,
        $port,
        realpath($docRoot)
    );

    // Execute the command and store the process ID
    $output = array();
    exec($command, $output);
    $pid = (int) $output[0];

    echo sprintf(
        '%s - Web server started on %s:%d (%s) with PID %d',
        date('r'),
        $host,
        $port,
        realpath($docRoot),
        $pid
    ) . PHP_EOL;

    // Kill the web server when the process ends
    register_shutdown_function(function () use ($pid) {
        echo sprintf('%s - Killing process with ID %d', date('r'), $pid) . PHP_EOL;
        exec('kill ' . $pid);
    });
}
