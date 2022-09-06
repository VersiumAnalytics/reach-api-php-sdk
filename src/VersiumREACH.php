<?php
namespace VersiumREACH;
class VersiumREACH
{
    private $apiKey;
    public $CURLOPT_CONNECTTIMEOUT;
    public $CURLOPT_TIMEOUT;
    public $verbose;
    public $waitTime; //microseconds

    function __construct(string $apiKey, int $CURLOPT_CONNECTTIMEOUT = 5, int $CURLOPT_TIMEOUT = 10, bool $verbose = false, int $waitTime = 2000000)
    {
        $this->apiKey = $apiKey;
        $this->CURLOPT_CONNECTTIMEOUT = $CURLOPT_CONNECTTIMEOUT;
        $this->CURLOPT_TIMEOUT = $CURLOPT_TIMEOUT;
        $this->verbose = $verbose;
        $this->waitTime = $waitTime;
    }

    /**
     * This function should be used to effectively query Versium REACH APIs. See our API Documentation for more information
     * https://api-documentation.versium.com/reference/welcome
     *
     * @param  string $dataTool
     * the current options for dataTool are: contact, demographic, b2cOnlineAudience, b2bOnlineAudience, firmographic, c2b, iptodomain
     * @param  array  $inputData
     * Each index of the inputData array should contain an array of key value pairs where the keys are the header names and the values are the value of the contact for that specific header
     * ex. $inputData[0] = ["first" => "someFirstName", "last" => "someLastName", "email" => "someEmailAddress"];
     * @param  array  $outputTypes
     * This array should contain a list of strings where each string is a desired output type. This parameter is optional if the API you are using does not require output types
     * @return array
     */
    public function append(string $dataTool, array $inputData, array $outputTypes = [])
    {
        $retryUrls = [];
        $failedRequests = 0;
        $requests = [];
        $active = 0;
        $multiHandle = curl_multi_init();
        $urls = [];
        $baseURL = "https://api.versium.com/v2/$dataTool?";
        
        if (empty($inputData)) {
            if ($this->verbose) {
                echo "No input data was given\n";
            }
            return [];
        }

        foreach ($outputTypes as $outputType) {
            $baseURL .= "output[]=$outputType&";
        }
        $counter = 0;
        foreach ($inputData as $row) {
            $urls[$counter] = $baseURL . http_build_query($row);
            $counter++;
        }



        $this->buildRequests($urls, $multiHandle, $requests);

        //OPTION 1
        $firstAttemptComplete = false;
        $hasAddedRetryHandles = false;
        $retryUrlCount = 0;
        $urlCount = count($urls);
        do {
            $status = curl_multi_exec($multiHandle, $active);
            while (!$firstAttemptComplete && (false !== ($info = curl_multi_info_read($multiHandle)))) {
                $urlCount--;
                if (curl_getinfo($info['handle'], CURLINFO_HTTP_CODE) == 429 || curl_getinfo($info['handle'], CURLINFO_HTTP_CODE) == 500) {
                    array_push($retryUrls, curl_getinfo($info['handle'], CURLINFO_EFFECTIVE_URL));
                    $retryUrlCount++;
                }
            }
            if (!$firstAttemptComplete && $urlCount == 0) {
                $firstAttemptComplete = true;
            }
            if ($firstAttemptComplete && !$hasAddedRetryHandles) {
                $this->buildRequests($retryUrls, $multiHandle, $requests);
                usleep($this->waitTime);
                $hasAddedRetryHandles = true;
                $status = curl_multi_exec($multiHandle, $active);
            }
            usleep(10000);

        } while ($active > 0 && $status == CURLM_OK);
        $numRetriedUrls = $retryUrlCount;
        
    
        //OPTION 2
        //send off all initial requests
        //$numRetriedUrls = count($retryUrls);
        // do {
        //     $status = curl_multi_exec($multiHandle, $active);
        //     while (false !== ($info = curl_multi_info_read($multiHandle))) {
        //         if (curl_getinfo($info['handle'], CURLINFO_HTTP_CODE) == 429 || curl_getinfo($info['handle'], CURLINFO_HTTP_CODE) == 500) {
        //             array_push($retryUrls, curl_getinfo($info['handle'], CURLINFO_EFFECTIVE_URL));
        //         }
        //     }
        //     usleep(10000);
        // } while ($active > 0 && $status == CURLM_OK);
        // $numRetriedUrls = count($retryUrls);

        // //retry requests that failed the first time after wait time is complete
        // if ($numRetriedUrls > 0) {
        //     usleep($this->waitTime);
        //     $this->buildRequests($retryUrls, $multiHandle, $requests);
        //     do {
        //         $status = curl_multi_exec($multiHandle, $active);
        //         usleep(10000);
        //     } while ($active > 0 && $status == CURLM_OK);
        // }

        //now put all the response data in $recs, close all requests, and return $recs
        $emptyResponses = 0;
        for ($i = 0; $i < count($requests) - $emptyResponses; $i++) {
            $errorNum = curl_errno($requests[$i]);
            $httpStatus = curl_getinfo($requests[$i], CURLINFO_HTTP_CODE);
            $data = curl_multi_getcontent($requests[$i]);
            $headerSize = curl_getinfo($requests[$i], CURLINFO_HEADER_SIZE);
            $responseHeader = substr($data, 0, $headerSize);
            $response = substr($data, $headerSize);
            curl_multi_remove_handle($multiHandle, $requests[$i]);
            curl_close($requests[$i]);
            if ($this->verbose && ($errorNum || $httpStatus != 200)) {
                if ($httpStatus == 0 || $errorNum == 28) { //status:0 == connection lost, curl err 28 == timeout
                    echo ("connection lost or timed out\n");
                } else {
                    $failedRequests++;
                }
            } 
            if (!empty($response)) {
                $recs[$i] = [
                    "response" => json_decode($response),
                    "http_status" => $httpStatus,
                    "response_header" => $responseHeader
                ];
            }
        }

        curl_multi_close($multiHandle);

        if ($this->verbose) {
            print_r($recs);
            print_r("Number of records submitted to the append function: " . count($urls) . "\n");
            print_r("Number of requests that failed on initial attempt due to 429 or 500 error code: " . $numRetriedUrls . "\n");
            print_r("Number of requests that were successfully retried: " . ($numRetriedUrls - $failedRequests) . "\n");
            print_r("Number of requests that failed (on both initial and retry attempt): " . $failedRequests . "\n");
            if ($recs) {
                print_r("Number of response records returned: " . count($recs) . "\n");
            } else {
                print_r("Number of response records returned: 0");
            }
        }
        return $recs;
    }

    private function buildRequests(array $urls, &$multiHandle, array &$requests)
    {
        $requestLength = count($requests) == 0 ? count($requests) : count($requests) - count($urls); //prevents writing over original requests
        foreach ($urls as $i => $url) {
            $requests[$i + $requestLength] = curl_init();
            $headers = array(
                "Accept: application/json",
                "x-versium-api-key: " . $this->apiKey,
            );

            curl_setopt($requests[$i + $requestLength], CURLOPT_URL, $url);
            curl_setopt($requests[$i + $requestLength], CURLOPT_HTTPHEADER, $headers);
            curl_setopt($requests[$i + $requestLength], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($requests[$i + $requestLength], CURLOPT_CONNECTTIMEOUT, $this->CURLOPT_CONNECTTIMEOUT);
            curl_setopt($requests[$i + $requestLength], CURLOPT_TIMEOUT, $this->CURLOPT_TIMEOUT);
            curl_setopt($requests[$i + $requestLength], CURLOPT_HEADER, true);
            curl_setopt($requests[$i + $requestLength], CURLOPT_FAILONERROR, true);

            curl_multi_add_handle($multiHandle, $requests[$i + $requestLength]);
        }
    }
}