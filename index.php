<?php

ini_set('error_reporting', E_ALL);
error_reporting(E_ALL);
ini_set('log_errors', true);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . 'error.log');
date_default_timezone_set('America/New_York');


require_once 'class/confidentCaptcha.class.php';
require_once 'class/phasher.class.php';
require_once 'class/predis/autoload.php';


$letters = !empty($_POST['letters']) ? $_POST['letters'] : false;
$solvedLetters = !empty($_POST['sletters']) ? $_POST['sletters'] : false;
$words = @is_array($_POST['words']) ? $_POST['words'] : false;
$image = !empty($_POST['image']) ? base64_decode($_POST['image']) : false;

if ($letters === false || $words === false || $image === false) {
    echo json_encode(array('success' => false, 'error' => 'Invalid Request'));
    exit;
}

$client = new Predis\Client(['timeout' => 0.5]);
try {
    if ($client->exists($_POST['licenseKey']) === false) {
        echo json_encode(array('success' => false, 'result' => 'Access Denied'));
        exit;
    }
} catch (Predis\Connection\ConnectionException $e) {
    // trigger_error("Captcha Breaker: cannot connect to Radis Server", E_USER_ERROR);
}

$dist = true;
$confident = new confidentCaptcha(dirname(__file__). '/newimages', dirname(__file__). '/tmp', $image, $words, $letters);
$confident->logfile = dirname(__file__) . '/log.txt';
$confident->log("=====================================\n", false);
$confident->log("License Key: " . $_POST['licenseKey']);
if ($solvedLetters !== false && strlen($solvedLetters) == 4) {
    $result = $confident->crack();
    if ($result === false) {
        if ($solvedLetters != $result) {
            $confident->log("Submitted Answer: {$solvedLetters}");
        }

        $count = $confident->addSolvedImages($solvedLetters);
        echo json_encode(array('success' => true, 'result' => 'Thank you. the image has been added to database.'));

        $confident->log('Added '.$count.' images to database.');
    } else {

        echo json_encode(array('success' => false, 'result' => 'Sorry but the image is already exists in database.'));
    }
} else {
    $result = $confident->crack();
    if ($result !== false) {
        echo json_encode(array('success' => true, 'result' => $result));
    } else {
        echo json_encode(array('success' => false, 'result' => 'Failed to crack CAPTCHA'));
    }
}

@ob_flush();
@flush();


$confident->saveLog();

if ($dist === true) {
    $confident->destroy();
}
