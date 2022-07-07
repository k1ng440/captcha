<?php
class confidentCaptcha {
    public $newImageFolder;
    private $tmpImage;
    private $keywords;
    private $letters;
    private $solvedLetters;
    public $autoSolvedLetters;
    public $savekeywords;
    public $PHasher;
    public $logfile = false;
    public $logs = [];
    public $dbfolder;
    private $imghaash;


    public function __construct($newImageFolder, $tmpfolder, $image, $keywords, $letters) {
        $this->keywords = array();
        foreach ($keywords as $keyword) {
            $this->keywords[] = str_replace(array('airplanes', 'dogs', 'clocks', 'computers', 'musical instruments', 'trains', 'birds', 'flowers', 'houses', 'peoples', 'telephones', 'beverages', 'cats', 'boats', 'cars'), array('airplane', 'dog' , 'clock' , 'computer', 'musical instrument', 'train', 'bird', 'flower', 'house', 'people', 'telephone', 'beverage', 'cat', 'boat', 'car'), $keyword);
        }

        $this->savekeywords = $this->keywords;

        $this->PHasher = PHasher::Instance();
        $tmp = uniqid(time());
        $this->newImageFolder = $newImageFolder . DIRECTORY_SEPARATOR . $tmp;
        $this->tmpImage = $tmpfolder . DIRECTORY_SEPARATOR . $tmp.'.jpg';
        $this->dbfolder = dirname(__DIR__) . '/db';
        $this->imghaash = array();
        file_put_contents($this->tmpImage, $image);

        $this->letters = $letters;

        $this->splitImages();

    }

    public function crack() {
        $captchaCode = array('', '', '', '');
        $solvedImages = [];
        $i = 0;

        $images = glob($this->newImageFolder .'/*.jpg');

        $this->log("keywords: " . implode(", ", $this->keywords));
        $this->log("Laters: {$this->letters}");
        $this->log("-------------------------------\r\n", false);

        foreach($images as $image) {
            $i++;
            $matchResult = $this->findExistingHash($image);
            if ($matchResult['found'] === true) {
                $msg = '';
                foreach ($this->keywords as $key => $keyword) {
                    if (strpos($keyword, $matchResult['keyword']) !== false) {
                        $msg = " ---> Keyword Matched.";
                        $captchaCode[$key] = $matchResult['letter'];
                        unset($this->keywords[$key]);
                    }
                }

                $this->log("Hash Found: {$matchResult['hash']}, Keyword: {$matchResult['keyword']}, Letter: {$matchResult['letter']}, %: {$matchResult['percent']} {$msg}");
            }
        }

        $this->log("-------------------------------\r\n", false);


        $this->autoSolvedLetters = $captchaCode;
        $solved = implode('', $captchaCode);
        $this->log("RESULT: ". $solved);
        if (strlen($solved) < 4) { // captcha failed to solve. keep the images and save question.
            $this->log('No solution');
            $this->RemoveSolvedImages(); // remove solved images.
            return false;
        } else { // captcha has been solved.
            $this->rmdir($this->newImageFolder); // remove all images
            return $solved;
        }
    }

    public function findExistingHash($newImage) {
        $newImageHash = $this->PHasher->HashAsString($this->PHasher->FastHashImage($newImage, $size=20), $hex=true);
        $letter = explode('_', basename($newImage));
        $letter = str_replace('.jpg', '', $letter[1]);
        $this->imghaash[$letter] = $newImageHash;

        foreach ($this->keywords as $kkey => $keyword) {
            $database = $this->dbfolder . '/' . $keyword . '.db';
            if (file_exists($database) === true) {
                $hashes = explode("\n", file_get_contents($database));
                foreach ($hashes as $hash) {
                    similar_text($newImageHash, $hash, $percent);
                    if (intval($percent) >= 80) {
                        return array(
                            'hash' => $hash,
                            'keyword' => $keyword,
                            'keyword_key' => $kkey,
                            'letter' => $letter,
                            'percent' => $percent,
                            'found' => true
                        );
                    }
                }
            }
        }

        return array(
            'hash' => $newImageHash,
            'keyword' => null,
            'letter' => $letter,
            'percent' => 0,
            'found' => false
        );
    }

    public function splitImages() {
        @mkdir($this->newImageFolder);
        $letters = explode("|", rtrim(chunk_split($this->letters, 3, "|"), '|'));
        $width = 120;
        $height = 90;
        $source = imagecreatefromjpeg($this->tmpImage);
        $source_width = imagesx($source);
        $source_height = imagesy($source);

        for ($col = 0; $col < $source_width / $width; $col++) {
            for ($row = 0; $row < $source_height / $height; $row++) {
                $fn = sprintf("%s/%01d%01d_%s.jpg", $this->newImageFolder, $row, $col, $letters[$row][$col]);
                $im = @imagecreatetruecolor($width, $height);
                imagecopyresized( $im, $source, 0, 0,
                    $col * $width, $row * $height, $width, $height,
                    $width, $height );
                imagejpeg( $im, $fn );
                imagedestroy( $im );
            }
        }
    }

    public function addSolvedImages($solvedLetters) {
        $solvedLetters = explode("|", rtrim(chunk_split($solvedLetters, 1, "|"), '|'));
        $count = 0;
        foreach ($solvedLetters as $key => $solvedLetter) {
            if (in_array($solvedLetter, $this->autoSolvedLetters) === true) {
                continue;
            }

            $hash = $this->imghaash[$solvedLetter];
            $keyword = $this->savekeywords[$key];
            file_put_contents($this->dbfolder . '/' . $keyword . '.db', $this->imghaash[$solvedLetter] . "\n", FILE_APPEND | LOCK_EX);

            $image = glob(sprintf("%s/*%s.jpg", $this->newImageFolder , $solvedLetter));
            @mkdir(dirname(__DIR__) .'/unmatched/'. $keyword);
            copy($image[0], dirname(__DIR__) .'/unmatched/'. $keyword .'/'. basename($this->newImageFolder) . '.jpg');

            $count++;
        }

        $this->rmdir($this->newImageFolder);
        return $count;
    }

    public function RemoveSolvedImages() {
        $images = glob(sprintf("%s/{*%s}.jpg", $this->newImageFolder, implode(',*', $this->autoSolvedLetters)), GLOB_BRACE);
        foreach ($images as $image) {
            @unlink($image);
        }
        @file_put_contents($this->newImageFolder . '/question.txt', implode(', ', $this->keywords));
    }

    public function result() {

    }

    public function destroy() {
        @unlink($this->tmpImage);
    }


    public function getTime($format = "Y-m-d H:i:s.u") {
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.'. $micro, $t));
        return $d->format($format);
    }


    public function log($log, $time = true) {
        if ($time === true) {
            $time = $this->getTime();
            $log = "[". $time ."] ". $log . "\r\n";
        }

        $this->logs[] = $log;
    }


    public function saveLog() {
        if ($this->logfile !== false) {
            file_put_contents($this->logfile, implode('', $this->logs), FILE_APPEND);
        } else {
            echo implode('', $this->logs);
        }
    }


    public function rmdir($dirPath) {
        $it = new RecursiveDirectoryIterator($dirPath);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dirPath);
    }

}
