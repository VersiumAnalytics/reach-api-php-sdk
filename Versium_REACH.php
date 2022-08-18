<?php
class VersiumREACH {

    /**
     * This function should be used to effectively query Versium REACH APIs. See our API Documentation for more information
     * https://api-documentation.versium.com/reference/welcome
     *
     * @param  array  $inputData
     * Each index of the inputData array should contain an array of key value pairs where the keys are the header names and the values are the value of the contact for that specific header
     * ex. $inputData[0] = ["first" => "someFirstName", "last" => "someLastName", "email" => "someEmailAddress"];
     * @param  array  $outputTypes
     * This array should contain a list of strings where each string is a desired output type. This parameter is optional if the API you are using does not require output types
     * @param  string $apiKey
     * @param  string $dataTool
     * the current options for dataTool are: contact, demographic, b2cOnlineAudience, b2bOnlineAudience, firmographic, c2b, iptodomain
     * @return array
     */
    public static function append(array $inputData, array $outputTypes = [], string $apiKey, string $dataTool) {
        $requests = array();
        $Recs = array_fill_keys(array_keys($inputData), '');
        $active = 0;
        $multiHandle = curl_multi_init();
        $urls = array();
        $baseURL = "https://api.versium.com/v2/$dataTool?";
        if (sizeof($outputTypes) > 0) {
            foreach($outputTypes as $outputType) {
                $baseURL .= "output[]=$outputType&";
            }
        }
        if (sizeof($inputData) > 0) {
            $counter = 0;
            foreach($inputData as $row) {
                $rowInputParams = "";
                foreach($row as $header => $value) {
                    $rowInputParams .= "$header=$value&";
                }
                $rowInputParams = substr($rowInputParams, 0, -1); //removes last & from the input params
                $urls[$counter] = $baseURL . $rowInputParams;
                if (isset($urls[$counter])) {
                    echo "urls[$counter]: " . $urls[$counter] . "\n";
                } else {
                    echo "urls[$counter] is not defined\n";
                }
                $counter++;
            }
        } else {
            echo "No input data was given\n";
            return;
        }

        foreach ($urls as $i => $url) {
            $requests[$i] = curl_init();
            $headers = array(
                "Accept: application/json",
                "x-versium-api-key: $apiKey",
            );

            curl_setopt($requests[$i], CURLOPT_URL, $url);
            curl_setopt($requests[$i], CURLOPT_HTTPHEADER, $headers);
            curl_setopt($requests[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($requests[$i], CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($requests[$i], CURLOPT_TIMEOUT, 10);

            curl_multi_add_handle($multiHandle, $requests[$i]);
        }

        do {
            $status = curl_multi_exec($multiHandle, $active);
            usleep(10000);
        } while ($active > 0 && $status == CURLM_OK);

        // now get all data and close all requests and return records.
        foreach ($urls as $i => $url) {
            $error = null;
            $errorNum = curl_errno($requests[$i]);
            $httpStatus = curl_getinfo($requests[$i], CURLINFO_HTTP_CODE);

            $data = curl_multi_getcontent($requests[$i]);
            
            $Recs[$i] = [];

            if ($errorNum || $httpStatus != 200) {
                if ($httpStatus === 0 || $errorNum === 28) { // status:0 == connection lost, curl err 28 == timeout
                    echo("connection lost or timed out\n");
                } else {
                    echo("httpStatus: $httpStatus\n");
                }
            } else {
                if (!empty($data)) {
                    $Recs[$i] = json_decode($data);
                }
            }

            curl_multi_remove_handle($multiHandle, $requests[$i]);
            curl_close($requests[$i]);
        }
        // now close the multi get handle
        curl_multi_close($multiHandle);

        return $Recs;
    }
}