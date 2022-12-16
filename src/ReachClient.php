<?php
namespace Versium\Reach;


use Generator;

class ReachClient
{
    private $apiKey,
            $logger,
            $startTime;
    public $maxRetries = 3,
           $version = 2,
           $qps,
           $connectTimeout = 5,
           $timeout = 10,
           $maxBatchRequestTime = 20,
           $waitTime = 2000000; //microseconds

    /**
     * @param string $apiKey
     * @param int $qps
     * @param callable|null $loggingFunction
     */
    public function __construct(string $apiKey, callable $loggingFunction = null, int $qps = 20)
    {
        $this->apiKey = $apiKey;
        $this->logger = $loggingFunction;
        $this->qps = $qps;
        $this->startTime = microtime(true);
    }

    //region Protected helper functions
    /**
     * @param string $msg
     * @return void
     */
    protected function log(string $msg) {
        if ($this->logger)
            ($this->logger)($msg);
    }

    /**
     * @param array $requests
     * @return array
     */
    protected function sendRequests(array $requests): array
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

            curl_multi_add_handle($multiHandle, $channels[$i]);
        }

        $time1 = microtime(true);
        $this->log('sendRequests::Sending requests: ' . json_encode($requests));

        do {
            $time2 = microtime(true);
            curl_multi_exec($multiHandle, $active);
            usleep(10000);
        } while ($active > 0 && ($time2 - $time1) < $this->maxBatchRequestTime);

        foreach ($requests as $i => list('inputs' => $inputs)) {
            $headerSize = curl_getinfo($channels[$i], CURLINFO_HEADER_SIZE);
            $data = curl_multi_getcontent($channels[$i]);
            $response = substr($data, $headerSize);
            $body = json_decode($response);
            $statusCode = curl_getinfo($channels[$i], CURLINFO_HTTP_CODE);
            $curlError = curl_errno($channels[$i]);

            $results[$i] = [
                "requestErrorNum" => $curlError,
                'requestError' => curl_error($channels[$i]),
                "httpStatus" => $statusCode,
                "headers" => substr($data, 0, $headerSize),
                "bodyRaw" => $response,
                "body" => $body,
                'matchFound' => !empty($body->versium->num_matches),
                'success' => $statusCode == 200,
                'inputs' => $inputs
            ];

            curl_multi_remove_handle($multiHandle, $channels[$i]);
            curl_close($channels[$i]);
        }

        $this->log('sendRequests::results: ' . json_encode($results));

        return $results;
    }

    /**
     * @param array $results
     * @param array $requests
     * @param int $retries
     * @return void
     */
    protected function sendAndRetryRequests(array &$results, array &$requests, int $retries): void
    {
        $newResults = $this->sendRequests($requests);
        $results = empty($results) ? $newResults : array_replace($results, $newResults);

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
            $this->sendAndRetryRequests($results, $requests, $retries);
        }
    }
    //endregion

    //region public API request functions

    /**
     * This function should be used to effectively query Versium REACH APIs. See our API Documentation for more information
     * https://api-documentation.versium.com/reference/welcome
     *
     * @param string $dataTool
     * The current options for dataTool are: contact, demographic, b2cOnlineAudience, b2bOnlineAudience, firmographic, c2b, iptodomain
     * @param array $inputData
     * Each index of the inputData array should contain an array of key value pairs where the keys are the header names and the values are the value of the contact for that specific header
     * ex. $inputData[0] = ["first" => "someFirstName", "last" => "someLastName", "email" => "someEmailAddress"];
     * @param array $outputTypes
     * This array should contain a list of strings where each string is a desired output type. This parameter is optional if the API you are using does not require output types
     * @return Generator
     */
    public function append(string $dataTool, array $inputData, array $outputTypes = []): Generator
    {
        $requests = [];
        $ctr = 0;
        $baseURL = "https://api.versium.com/v" . $this->version . "/" . urlencode($dataTool) . "?";

        if (empty($inputData)) {
            $this->log("append::No input data was given.");
            yield [];
        }

        foreach ($outputTypes as $outputType) {
            $baseURL .= "output[]=" . urlencode($outputType) . "&";
        }

        foreach ($inputData as $i => $row) {
            $ctr++;
            $requests[$i] = [
                'url' => $baseURL . http_build_query($row),
                'headers' => [
                    "Accept: application/json",
                    "x-versium-api-key: " . $this->apiKey,
                ],
                'inputs' => $row
            ];

            if ($ctr >= $this->qps) {
                yield $this->createAndLimitRequests($requests);

                $ctr = 0;
                $requests = [];
            }
        }

        if (!empty($requests)) {
            yield $this->createAndLimitRequests($requests);
        }
    }

    /**
     * @param array $requests
     * @return array
     */
    protected function createAndLimitRequests(array $requests): array
    {
        $results = [];
        $this->log("createRequests::Created requests: " . json_encode($requests));
        $remainingTime = 1100000 - ((microtime(true) - $this->startTime) * 1000000);

        if ($remainingTime > 0) {
            $this->log("createRequests::Sleeping for " . $remainingTime);
            usleep($remainingTime);
        }

        $this->startTime = microtime(true);
        $this->sendAndRetryRequests($results, $requests, 0);
        $this->log("createRequests::Final results: " . json_encode($results));
        $this->log("createRequests::Failed requests: " . json_encode($requests));

        return $results;
    }
    //endregion
}
