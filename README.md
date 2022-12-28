# Versium REACH API Software Development Kit (SDK)
A simplified PHP interface for accessing the [Versium Reach APIs](https://api-documentation.versium.com/docs/start-building-with-versium)

## Installation
From the root of your project, use composer to install the SDK from [packagist](https://packagist.org/).
```bash
composer require versium/reach-api-php-sdk
```

## Usage
1) Use `ReachClient`
```php
use Versium\Reach\ReachClient;
```

2) Create a `ReachClient` instance, with your [API Key](https://app.versium.com/account/manage-api-keys), an optional callback function for logging, and an optional queries-per-second rate limit.
```php
$loggingFunction = function($msg) {
    //print out message for dev
    echo $msg;
};
$client = new ReachClient('you-api-key', $loggingFunction);
```

3) For adding data to a set of inputs, use the `append` function. This function returns a `Generator` that yields arrays containing API responses. Check the [API documentation](https://api-documentation.versium.com/docs/the-versium-api-landscape) for which data tools and output types are available.  
```php
//call the contact API to append phones and emails 
$inputs = [
    [
        'first' => 'john',
        'last' => 'doe',
        'address' => '123 Trinity St',
        'city' => 'Redmond',
        'state' => 'WA',
        'zip' => '98052',
    ]
];

foreach ($client->append('contact', $inputs, ['email', 'phone']) as $results) {
    //filter out failed queries for processing later
    $failedResults = array_filter($results, function ($result) {
        return !$result->success;
    });
    
    //merge successful matches with inputs
    foreach ($results as $idx => $result) {
        if ($result->matchFound) {
            $inputs[$idx]['appendResults'] = $result->body->versium->results;
        }        
    }
}
```

4) For retrieving a list of records, use the `listgen` function. This function returns a single `APIResponse` object. This object contains the getRecordsFunc property for iterating through the returned records. Check the [API documentation](https://api-documentation.versium.com/docs/the-versium-api-landscape) for which data tools and output types are available.
```php
$result = $client->listgen('abm', ['domain' => ['versium.com']], ['abm_email', 'abm_online_audience']);

if ($result->success) {
    foreach (($result->getRecordsFunc)() as $record) {
        var_dump($record);
    }
}
```

## Returned Results
Both the `append` and `listgen` functions return one or more `APIResponse` objects. See the comments in the class for descriptions of its properties. 


# Things to keep in mind
- The default rate limit for Reach APIs is 20 queries per second
- You must have a provisioned API key for this function to work. If you are unsure where to find your API key,
  look at our [API key documentation](https://api-documentation.versium.com/docs/find-your-api-key)
