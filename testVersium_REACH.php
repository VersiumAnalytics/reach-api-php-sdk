<?php

/* THIS FILE IS JUST FOR TESTING - MAKE SURE TO DELETE ONCE SDK IS COMPLETE */
include ("Versium_REACH.php");

$inputData = array_fill(0, 30, ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"]);
//$inputData[0] = ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"];
$apiKey = "";
$outputTypes = ["address", "phone"];
$dataTool = "contact";

$Versium_REACH = new VersiumREACH();

$responses = $Versium_REACH->append($inputData, $outputTypes, $apiKey, $dataTool);

print_r($responses);
