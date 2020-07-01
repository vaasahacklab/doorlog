<?php
// Setup autoloader
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

require 'config.php'; // Initializes $settings
require 'FuzzyTime.php';

// Instantiate a Slim application using its default settings.
$app = new \Slim\Slim();

// Settings
$app->config(
    ['debug' => isset($settings['debug']) ? (bool)$settings['debug'] : false]
);

function logiin($phoneNumber, $message = '', $logfile = '') {
    if (empty($message)) {
        $logString = date(DATE_W3C) . ', ' . $phoneNumber . "\n";
    } else {
        $logString = date(DATE_W3C) . ', ' . $phoneNumber . ', ' . $message . "\n";
    }

    file_put_contents($logfile, $logString, FILE_APPEND);
}

/**
 * Handle index view
 */
$app->get(
    '/',
    function () use ($app) {
        $app->render(
            'message.php',
            [
                'title' => 'Hei',
                'message' => 'Käy <a href="http://vaasa.hacklab.fi/">Vaasa Hacklabin sivuilla</a>'
            ]
        );
    }
)->name('index');

/**
 * Handle logging phone number and optional message
 */
$app->post(
    '/log/',
    function () use ($app, $settings) {
        $apiKey = $app->request->post('key');
        $phoneNumber = $app->request->post('phone');
        $message = $app->request->post('message');

        // Check API key parameter
        if (trim($apiKey) !== $settings['api_key']) {
            $app->halt(403, 'API key access denied');
        }

        // Clean up POST data
        $phoneNumber = preg_replace('/[^\d\w\b -.,:;]/', '', $phoneNumber);
        $message = preg_replace('/[^\d\w\b -.,:;]/', '', $message);

        try {
            logiin($phoneNumber, $message, $settings['log_file']);
        } catch (Exception $e) {
            echo "FAIL: " . $e->getMessage();
            return;
        }

        echo 'OK';
    }
);

$app->get(
    '/newest',
    function () use ($app, $settings) {
        // Get last 1000 characters from log file, usually enough
        $log = substr(
            file_get_contents($settings['log_file']),
            -1000
        );

        // Turn log content into array and reverse it, now newest line is first
        $log_array = explode("\n", $log);
        $log_array = array_reverse($log_array);

        $result = [];

        foreach($log_array as $row) {
            // Skip empty rows from log array
            if(empty($row)) {
                continue;
            }

            // Get info from row
            $info = explode(', ', $row);
            $timestamp = strtotime($info[0]);
            $username = trim(utf8_decode($info[2]));

            if($username === 'boot' || strtolower($username) === 'denied') {
                continue;
            }

        break;
        }

        $result = 'Door last opened by \'' . $username . '\' ' . FuzzyTime::getFuzzyTime($timestamp);

        echo $result;
    }
);

$app->run();
