<?php

/* THIS FILE IS JUST FOR TESTING - MAKE SURE TO DELETE ONCE SDK IS COMPLETE */
include ("Versium_REACH.php");

$inputData = array_fill(0, 40, ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"]);
//$inputData[0] = ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"];
$apiKey = "949aae79-8415-45a1-8b99-b1ec4ea7c2cc";
$outputTypes = ["address", "phone"];
$dataTool = "contact";

$Versium_REACH = new VersiumREACH($apiKey, 5, 10, true);

$responses = $Versium_REACH->append($dataTool, $inputData, $outputTypes);