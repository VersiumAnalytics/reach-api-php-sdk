<?php
require_once __DIR__ . '/../vendor/autoload.php';
use VersiumREACH\VersiumREACH;
/* THIS FILE IS JUST FOR TESTING - MAKE SURE TO DELETE ONCE SDK IS COMPLETE */

class testVersium_REACH {
    function __construct() {

    }

    public function runTests() {
        $inputData = array_fill(0, 40, ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"]);
        //$inputData[0] = ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"];
        $apiKey = "";
        $outputTypes = ["address", "phone"];
        $dataTool = "contact";
    
        $Versium_REACH = new VersiumREACH($apiKey, 5, 10, true);
        $responses = $Versium_REACH->append($dataTool, $inputData, $outputTypes);
    }
}

$test = new testVersium_REACH();
$test->runTests();