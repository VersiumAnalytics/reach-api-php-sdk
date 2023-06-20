<?php
namespace Versium\Reach;


use Exception;
use Generator;

class ReachClient
{
    private $apiKey,
            $logger,
            $startTime;
    public $maxRetries = 3,             //how many times to retry a query, if the server returns a 500 or 429
           $version = 2,                //which version of the API to use
           $qps,                        //how many queries to make in a single batch request
           $connectTimeout = 5,         //seconds
           $timeout = 10,               //seconds
           $streamTimeout = 300,        //timeout in seconds for a listgen request - should be high
           $maxBatchRequestTime = 20,   //total number of seconds to allow a single batch of requests to run
           $waitTime = 2000000;         //how many microseconds to wait before retrying a failed request

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
     * @param array $config
     * @return Generator
     */
    public function append(string $dataTool, array $inputData, array $outputTypes = [], array $config = []): Generator
    {
        $requests = [];
        $ctr = 0;
        $baseURL = $this->constructAPIURL($dataTool);
        $baseParams = [];

        if (empty($inputData)) {
            $this->log("append::No input data was given.");
            yield [];
        }

        foreach ($outputTypes as $outputType) {
            $baseURL .= "output[]=" . urlencode($outputType) . "&";
        }

        if ($this->timeout > 0) {
            $baseParams['rcfg_max_time'] = max($this->timeout - .2, .1);
        }

        if (!empty($config)) {
            $baseParams = array_replace($baseParams, $config);
        }

        foreach ($inputData as $i => $row) {
            $ctr++;
            $requests[$i] = [
                'url' => $baseURL . http_build_query(array_merge($row, $baseParams)),
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
     * Function for calling the REACH Listgen API
     * See documentation: https://api-documentation.versium.com/reference/account-based-list-abm
     * @param string $dataTool
     * @param array $inputs
     * @param array $outputTypes
     * @param string $tempFilePath - This function temporarily write the API results to a file. You can provide a
     *                               temporary file path; otherwise, it will use the default temp system dir.
     * @return APIResponse - Returned class contains a function that returns a generator.
     *                       This function should be used to iterate through the response records. Example:
     *                       $result = $client->listgen('abm', ['domain' => ['versium.com']], ['abm_email']);
     *                       foreach (($result->getRecordsFunc)() as $record) {};
     * @throws Exception
     */
    public function listgen(string $dataTool, array $inputs, array $outputTypes, string $tempFilePath = ''): APIResponse {
        if ($tempFilePath == '') {
            $tempFilePath = tempnam(sys_get_temp_dir(), 'reach_listgen_');
        }

        $fh = fopen($tempFilePath, 'w+');
        $requestParams = array_merge(['output' => $outputTypes], $inputs);
        $response = array_merge([
            'inputs' => $inputs,
        ], $this->sendListGenRequest($this->constructAPIURL($dataTool), $requestParams, $fh));

        //check for requests errors
        if (!empty($response['requestErrorNum'])) {
            @fclose($fh);
        } else {
            //get headers from file
            rewind($fh);
            $response['headers'] = $this->headersToArr(fread($fh, $response['headerSize']));

            //check for server/response errors
            if ($response['httpStatus'] != 200) {
                $response['bodyRaw'] = fread($fh, filesize($tempFilePath));
                $response['body'] = json_decode($response['bodyRaw'], true);
            } else {
                $response['success'] = true;
                $response['getRecordsFunc'] = function() use ($fh): Generator {
                    while (($json = fgets($fh)) !== false) {
                        yield json_decode($json, true);
                    }

                    @fclose($fh);
                };
            }
        }

        return new APIResponse($response);
    }
    //endregion

    //region HTTP helper functions
    /**
     * @param string $headers
     * @return array
     */
    protected function headersToArr(string $headers): array
    {
        $headers = array_filter(explode("\r\n", $headers));
        $headersArr = [];

        //Remove status message
        array_shift($headers);

        // Create an associative array containing the response headers
        foreach ($headers as $value) {
            if (false !== ($matches = explode(':', $value, 2))) {
                $headersArr["{$matches[0]}"] = trim($matches[1]);
            }
        }

        return $headersArr;
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

            $results[$i] = new APIResponse([
                "requestErrorNum" => $curlError,
                'requestError' => curl_error($channels[$i]),
                "httpStatus" => $statusCode,
                "headers" => $this->headersToArr(substr($data, 0, $headerSize)),
                "bodyRaw" => $response,
                "body" => $body,
                'matchFound' => !empty($body->versium->num_matches),
                'success' => $statusCode == 200,
                'inputs' => $inputs
            ]);

            curl_multi_remove_handle($multiHandle, $channels[$i]);
            curl_close($channels[$i]);
        }

        $this->log('sendRequests::results: ' . json_encode($results));

        return $results;
    }

    /**
     * @param string $url
     * @param array $postData
     * @param $fileHandle
     * @return array
     */
    protected function sendListGenRequest(string $url, array $postData, $fileHandle): array
    {
        $this->log(sprintf("sendListGenRequest::Sending listgen request to URL: %s, Post parameters: %s",
            $url,
            json_encode($postData)
        ));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_FILE => $fileHandle,
            CURLOPT_TIMEOUT => $this->streamTimeout,
            CURLOPT_HEADER => 1,
            CURLOPT_HTTPHEADER => [
                'x-versium-api-key: ' . $this->apiKey,
            ]
        ]);
        curl_exec($ch);

        return [
            'requestErrorNum' => curl_errno($ch),
            'requestError' => curl_error($ch),
            "httpStatus" => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'headerSize' => curl_getinfo($ch, CURLINFO_HEADER_SIZE)
        ];
    }
    //endregion

    //region Protected helper functions
    /**
     * @param string $dataTool
     * @return string
     */
    protected function constructAPIURL(string $dataTool): string
    {
        return 'https://api.versium.com/v' . $this->version . "/" . urlencode($dataTool) . "?";
    }

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
    protected function createAndLimitRequests(array $requests): array
    {
        $results = [];
        $retries = 0;
        $this->log("createAndLimitRequests::Created requests: " . json_encode($requests));
        //check if we need to sleep in order to avoid hitting qps rate limit
        $remainingTime = 1100000 - ((microtime(true) - $this->startTime) * 1000000);

        if ($remainingTime > 0) {
            $this->log("createAndLimitRequests::Sleeping for " . $remainingTime);
            usleep((int)$remainingTime);
        }

        while (count($requests) > 0 && $retries <= $this->maxRetries) {
            if ($retries > 0) {
                $this->log('createAndLimitRequests::Sleeping for ' . $this->waitTime);
                usleep($this->waitTime);
                $this->log('createAndLimitRequests::Sleeping done. Starting retries.');
            }

            $this->startTime = microtime(true);
            $results = array_replace($results, $this->sendRequests($requests));
            $this->log('createAndLimitRequests::try attempt: ' . $retries);
            $this->log('createAndLimitRequests::try requests count: ' . count($requests));

            foreach ($results as $i => $result) {
                if (!in_array($result->httpStatus, [429, 500])) {
                    unset($requests[$i]);
                    $results[$i] = $result;
                }
            }
            $retries++;
        }

        $this->log("createAndLimitRequests::Final results: " . json_encode($results));
        $this->log("createAndLimitRequests::Failed requests: " . json_encode($requests));

        return $results;
    }
    //endregion
}
