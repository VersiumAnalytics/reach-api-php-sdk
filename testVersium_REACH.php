<?php

/* THIS FILE IS JUST FOR TESTING - MAKE SURE TO DELETE ONCE SDK IS COMPLETE */
include ("Versium_REACH.php");

$inputData = array_fill(0, 40, ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"]);
//$inputData[0] = ["first" => "Angela", "last" => "Adams", "email" => "adamsangela@hotmail.com"];
$apiKey = "";
$outputTypes = ["address", "phone"];
$dataTool = "contact";

$Versium_REACH = new VersiumREACH($apiKey, 5, 10, true);
$responses = $Versium_REACH->append($dataTool, $inputData, $outputTypes);
