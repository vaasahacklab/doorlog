<?php
// Setup autoloader
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

require 'config.php'; // Initializes $settings
require 'FuzzyTime.php';

// Sanity check settings
if (!isset($settings['api_key'])) {
    throw new Exception('Error: API key not set in config', 1);
}
if (!isset($settings['log_file'])) {
    throw new Exception('Error: log file path not set in config', 1);
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
 * @param string $token   Phone number or other token to log
 * @param string $message Optional message to log, usually user nickname
 * @param string $logfile Path to log file
 *
 * @throws \Exception Error on missing data to log or write error
 *
 * @return void
 */
function logiin($token = '', $message = '', $logfile = '')
{
    // Check for at least one loggable data
    if (empty(trim($token)) && empty(trim($message))) {
        throw new \Exception('Log token or message missing', 1);
    }

    // Setup JSON formatted log row
    $logData = [
        'received_at' => (new DateTime())->format(DateTime::ISO8601),
        'token' => $token,
        'message' => $message
    ];

    $logString = json_encode($logData) . "\n";
    $result = file_put_contents($logfile, $logString, FILE_APPEND);

    // Check for possible error
    if ($result === false) {
        throw new \Exception('Log file writing failed', 1);
    }
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
 * Handle logging phone number and/or message
 */
$app->post(
    '/log/',
    function () use ($app, $settings) {
        // Get authorization
        $apiKey = $app->request->post('key');

        // Check API key parameter
        if (trim($apiKey) !== $settings['api_key']) {
            $app->halt(403, 'API key access denied');
        }

        // Get data for log
        $message = $app->request->post('message');
        $token = $app->request->post('phone');
        if (empty($token)) {
            $token = $app->request->post('token');
        }

        // Clean up POST data
        $token = preg_replace('/[^\d\w\b -.,:;]/', '', $token);
        $message = preg_replace('/[^\d\w\b -.,:;]/', '', $message);

        try {
            logiin($token, $message, $settings['log_file']);
        } catch (\Exception $e) {
            $app->halt(500, 'FAIL: ' . $e->getMessage());
        }

        echo 'OK';
    }
);

/**
 * Handle displaying latest logged event
 */
$app->get(
    '/newest',
    function () use ($app, $settings) {
        // Get last 1000 characters from log file, usually enough
        $log = substr(
            file_get_contents($settings['log_file']),
            -1000
        );

        // Turn log content into array and reverse it, now newest line is first
        $logRows = explode("\n", $log);
        $logRows = array_reverse($logRows);

        // Drop last line as it might be cut off and unable to be parse as JSON
        array_pop($logRows);

        $result = [];
        $skipUsernames = ['boot', 'denied'];
        foreach ($logRows as $row) {
            // Skip empty rows from log array
            if (empty($row)) {
                continue;
            }

            // Get log event info from row
            $event = json_decode($row);
            $timestamp = strtotime($event->received_at);
            $username = trim(strip_tags($event->message ?? 'somebody'));

            if (in_array(strtolower($username), $skipUsernames)) {
                continue;
            }

            break;
        }

        $result = sprintf(
            "Door last opened by %s %s",
            $username,
            FuzzyTime::getFuzzyTime($timestamp)
        );

        echo $result;
    }
);

$app->run();
