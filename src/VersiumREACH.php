<?php
namespace VersiumREACH;


class VersiumREACH
{
    private $apiKey;
    private $logger;
    public $maxRetries = 3;
    public $connectTimeout = 5;
    public $timeout = 10;
    public $maxBatchRequestTime = 20;
    public $verbose;
    public $waitTime = 2000000; //microseconds

    /**
     * @param string $apiKey
     * @param bool $verbose
     */
    public function __construct(string $apiKey, bool $verbose = false)
    {
        $this->apiKey = $apiKey;
        $this->verbose = $verbose;
    }

    //region Setters
    /**
     * @param callable $loggingFunction
     * @return void
     */
    public function setLogger(callable $loggingFunction) {
        $this->logger = $loggingFunction;
    }
    //endregion

    //region Private helper functions
    /**
     * @param string $msg
     * @return void
     */
    private function log(string $msg) {
        if ($this->verbose) {
            ($this->logger)($msg);
        }
    }

    /**
     * @param array $results
     * @param array $requests
     * @param int $retries
     * @return void
     */
    private function handleRequests(array &$results, array &$requests, int $retries): void
    {
        $results = array_replace($results, $this->sendRequests($requests));

        foreach ($results as $i => $result) {
            if (!in_array($result['httpStatus'], [429, 500, 0])) {
                unset($requests[$i]);
            }
        }

        if (count($requests) > 0 && $retries < $this->maxRetries) {
            $retries++;
            $this->log('handleRequests::Retry attempt: ' . $retries);
            $this->log('handleRequests::Retry requests count: ' . count($requests));
            $this->log('handleRequests::Sleeping for ' . $this->waitTime);
            usleep($this->waitTime);
            $this->log('handleRequests::Sleeping done. Starting retries.');
            $this->handleRequests($results, $requests, $retries);
        }
    }


    /**
     * @param array $requests
     * @return array
     */
    private function sendRequests(array $requests): array
    {
        $multiHandle = curl_multi_init();
        $results = [];
        $active = 0;
        $channels = [];

        foreach ($requests as $i => list('url' => $url, 'headers' => $headers)) {
            $channels[$i] = curl_init();

            if (!empty($headers)) {
                curl_setopt($channels[$i], CURLOPT_HTTPHEADER, $headers);
            }

            curl_setopt($channels[$i], CURLOPT_URL, $url);
            curl_setopt($channels[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($channels[$i], CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($channels[$i], CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($channels[$i], CURLOPT_HEADER, true);
            curl_setopt($channels[$i], CURLOPT_FAILONERROR, true);

            curl_multi_add_handle($multiHandle, $channels[$i]);
        }

        $time1 = microtime(true);
        $this->log('sendRequests::Sending requests: ' . json_encode($requests));

        do {
            $time2 = microtime(true);
            curl_multi_exec($multiHandle, $active);
            usleep(10000);
        } while ($active > 0 && ($time2 - $time1) < $this->maxBatchRequestTime);

        foreach ($requests as $i => $request) {
            $headerSize = curl_getinfo($channels[$i], CURLINFO_HEADER_SIZE);
            $data = curl_multi_getcontent($channels[$i]);
            $response = substr($data, $headerSize);

            $results[$i] = [
                "errorNum" => curl_errno($channels[$i]),
                "httpStatus" => curl_getinfo($channels[$i], CURLINFO_HTTP_CODE),
                "headers" => substr($data, 0, $headerSize),
                "bodyRaw" => $response,
                "body" => json_decode($response),
            ];

            curl_multi_remove_handle($multiHandle, $channels[$i]);
            curl_close($channels[$i]);
        }

        $this->log('sendRequests::results: ' . json_encode($results));

        return $results;
    }
    //endregion

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
    public function append(string $dataTool, array $inputData, array $outputTypes = []): array
    {
        $requests = [];
        $results = [];
        $baseURL = "https://api.versium.com/v2/" . urlencode($dataTool) . "?";
        
        if (empty($inputData)) {
            $this->log("append::No input data was given.");
            return [];
        }

        foreach ($outputTypes as $outputType) {
            $baseURL .= "output[]=" . urlencode($outputType) . "&";
        }
        foreach ($inputData as $row) {
            $requests[] = [
                'url' => $baseURL . http_build_query($row),
                'headers' => [
                    "Accept: application/json",
                    "x-versium-api-key: " . $this->apiKey,
                ]
            ];
        }

        $this->log("append::Created the following requests: " . json_encode($requests));
        $this->handleRequests($results, $requests, 0);
        $this->log("append::Final results: " . json_encode($results));
        $this->log("append::Failed requests: " . json_encode($requests));

        return $results;
    }
}
