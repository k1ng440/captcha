<?php
/**
 * @category PHP
 * @author   Steven King (info@k1ngdom.net) (skype: k1ngs.k1ngdom) (phone: +880 174 202 0548)
 * @link     http://k1ngdom.net
 */
require_once 'class/phasher.class.php';
$PHasher = PHasher::Instance();

$movefrom = array('airplanes', 'dogs', 'clocks', 'computers', 'musical instruments', 'trains', 'birds', 'flowers', 'houses', 'peoples', 'telephones', 'beverages', 'cats', 'boats', 'cars');
$moveto = array('airplane', 'dog' , 'clock' , 'computer', 'musical instrument', 'train', 'bird', 'flower', 'house', 'people', 'telephone', 'beverage', 'cat', 'boat', 'car');


$categories = glob(__DIR__ .DIRECTORY_SEPARATOR. 'unmatched' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

foreach ($categories as $catdir) {
    $images = glob($catdir .DIRECTORY_SEPARATOR. '*.jpg');
    foreach ($images as $image) {
        $hash[$image] = $PHasher->HashAsString($PHasher->FastHashImage($image, $size=20), $hex=true);
    }
}

foreach ($hash as $key => $find) {
    unset($hash[$key]);
    foreach ($hash as $imgpath => $findwith) {
        similar_text($find, $findwith, $percent);

        if (intval($percent) >= 90) {
            unset($hash[$imgpath]);
            unlink($imgpath);
            echo "
            find    : $find
            findwith: $findwith
            percent : $percent";

        }
    }
}


// create database.
foreach ($categories as $catdir) {
    $images = glob($catdir .DIRECTORY_SEPARATOR. '*.jpg');
    foreach ($images as $image) {
        $hash[$image] = $PHasher->HashAsString($PHasher->FastHashImage($image, $size=20), $hex=true);
    }
}

foreach ($hash as $filename => $hash) {
    file_put_contents(__DIR__ . '/db/' . basename(dirname($filename)) . '.db', $hash . "\n", FILE_APPEND | LOCK_EX);
    // basename(dirname($filename));
}