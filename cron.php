<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'class/predis/autoload.php';
$username = 'k1ngdom';
$password = 'fareze8s';

$process = curl_init('http://codegenie.co/paidlist/activelicenses.php');
curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($process, CURLOPT_HEADER, 0);
curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
curl_setopt($process, CURLOPT_TIMEOUT, 30);
curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
$return = curl_exec($process);

curl_close($process);

$json = json_decode($return);


if (null !== $json) {
    $client = new Predis\Client();
    try {
        $client->connect();
    } catch (Predis\Connection\ConnectionException $e) {
        trigger_error("Cron: cannot connect to Radis Server", E_USER_ERROR);
    }

    var_dump($client->flushdb());


    foreach ($json->licenses as $license) {
        $client->set($license, 1);
    }
}


