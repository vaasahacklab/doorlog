<?php
// Setup autoloader
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

require 'config.php'; // Initializes $settings
require 'FuzzyTime.php';

// Sanity check settings
if (!isset($settings['api_key'])) {
    throw new Exception("Error: API key not set in config", 1);
}
if (!isset($settings['log_file'])) {
    throw new Exception("Error: log file path not set in config", 1);
}

// Instantiate a Slim application using its default settings.
$app = new \Slim\Slim();

// Settings
$app->config(
    ['debug' => isset($settings['debug']) ? (bool)$settings['debug'] : false]
);

/**
 * Log door opening event to log file
 *
 * @param string $token   Phone number/token to log
 * @param string $message Optional message to log, usually user nick
 * @param string $logfile Path to log file
 *
 * @return void
 */
function logiin($token = '', $message = '', $logfile = '')
{
    if (empty($token) && empty($message)) {
        throw new \Exception("Provide token and/or message field", 1);
    }

    // TODO Prevent token from leaking to /newest/ view
    $logString = implode(', ', [date(DATE_W3C), $token, $message]) . "\n";

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
    '/log/?',
    function () use ($app, $settings) {
        $request = $app->request;

        // Get auth data
        $apiKey = '';
        $auth = $request->headers->get('Authorization');
        if (!empty($auth)) {
            // Handle format: Bearer xyz
            $authParts = explode(' ', $auth);
            $apiKey = array_pop($authParts);
        }
        if (empty($apiKey)) {
            $apiKey = $app->request->post('key');
        }

        // Get request logging data
        $token = $app->request->post('token');
        if (empty($token)) {
            $token = $app->request->post('phone');
        }
        $message = $app->request->post('message');

        // Check API key
        if (trim($apiKey) !== $settings['api_key']) {
            $app->halt(403, 'Access denied');
        }

        // Clean up POST data
        $token = preg_replace('/[^\d\w\b -.,:;]/', '', $token);
        $message = preg_replace('/[^\d\w\b -.,:;]/', '', $message);

        try {
            logiin($token, $message, $settings['log_file']);
        } catch (Exception $e) {
            echo "FAIL: " . $e->getMessage();
            return;
        }

        echo 'OK';
    }
);

$app->get(
    '/newest/?',
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
        $nickname = 'Unknown';
        $timestamp = 0;

        foreach ($log_array as $row) {
            // Skip empty rows from log array
            if (empty($row)) {
                continue;
            }

            // Get info from row
            $info = explode(', ', $row);
            $timestamp = strtotime(strip_tags($info[0]));

            $extractedNickname = strip_tags(trim(utf8_decode($info[2])));
            if ($extractedNickname === 'boot'
                || strtolower($extractedNickname) === 'denied'
            ) {
                continue;
            }

            $nickname = $extractedNickname;
            break;
        }

        $result = sprintf(
            "Door last opened by '%s' %s",
            $nickname,
            FuzzyTime::getFuzzyTime($timestamp)
        );

        echo $result;
    }
);

$app->run();
