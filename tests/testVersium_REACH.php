<?php
require_once __DIR__ . '/../vendor/autoload.php';
use VersiumREACH\VersiumREACH;
/* THIS FILE IS JUST FOR TESTING - MAKE SURE TO DELETE ONCE SDK IS COMPLETE */

class testVersium_REACH {
    function __construct() {

    }

    public function runTests() {
        $inputData = [];
//        $inputData = array_fill(0, 40, ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"]);
        $inputData[] = ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"];
        $inputData[] = ["first" => "roberta", "last" => "cacioppo", "email" => "robear28@aol.com"];
        $apiKey = "857d619e-f1e8-46e8-acea-bef952a4b1d3";
        $outputTypes = ["address", "phone"];
        $dataTool = "contact";
    
        $Versium_REACH = new VersiumREACH($apiKey,true);
        $Versium_REACH->setLogger(function($msg) {
            echo $msg, "\n\n";
        });
        $responses = $Versium_REACH->append($dataTool, $inputData, $outputTypes);
    }
}

$test = new testVersium_REACH();
$test->runTests();