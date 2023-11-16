<?php

require 'vendor/autoload.php';

use Aws\Iam\IamClient;
use GuzzleHttp\Client;

// Set your AWS credentials and region
$awsCredentials = [
    'region' => 'your-aws-region',
    'version' => 'latest',
    'credentials' => [
        'key' => 'your-aws-access-key-id',
        'secret' => 'your-aws-secret-access-key',
    ],
];

// Initialize IAM client
$iamClient = new IamClient($awsCredentials);

// Fetch IAM users
$iamUsers = $iamClient->listUsers();

// Third-party API endpoint to submit IAM users
$apiEndpoint = 'https://third-party-api.com/users';

// Initialize HTTP client
//$httpClient = new Client();

// Initialize HTTP client with custom headers
$httpClient = new Client([
    'headers' => [
        'Host' => 'rsupernova.com', // Add the desired Host header
        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0', // Add the desired User-Agent header
    ],
]);

// Iterate through IAM users
foreach ($iamUsers['Users'] as $iamUser) {
    $usernameToDelete = $iamUser['UserName'];

    // Submit user to API server
    $apiResponse = $httpClient->post($apiEndpoint, [
        'json' => ['username' => $usernameToDelete],
    ]);
        // Submit user to API server with custom headers
    try {
        $apiResponse = $httpClient->post($apiEndpoint, [
            'headers' => [
                'Content-Type' => 'application/json', // Add any other headers if needed
            ],
            'json' => ['username' => $usernameToDelete],
        ]);
    // Decode API response
    $apiResponseData = json_decode($apiResponse->getBody(), true);

        // Check API response and add IAM user to the list if true
        if ($apiResponseData === true || (isset($apiResponseData['success']) && $apiResponseData['success'] === true)) {
            $iamUsersWithTrueValue[] = $usernameToDelete;
         //   $iamClient->deleteUser(['UserName' => $usernameToDelete]);  // only uncomment this line after proper test. This will delete all matching users.
echo "<pre>";
            echo "IAM user '{$usernameToDelete}' deleted.\n" . PHP_EOL;
echo "</pre>";
        } else {
echo "<pre>";
            echo "Skipping IAM user '{$usernameToDelete}'. API response: {$apiResponse->getBody()}\n" . PHP_EOL;
echo "</pre>";
        }

        } catch (GuzzleHttp\Exception\ClientException $e) {
        // Capture and log the error details
        echo "Error details: " . $e->getResponse()->getBody() . "\n" . PHP_EOL;
        echo "HTTP Status Code: " . $e->getResponse()->getStatusCode() . "\n" . PHP_EOL;

        // Handle the error as needed...
    }
}



// List IAM users with a value of true from API response
echo "\nList of IAM users with value 'true' from API response:\n";
foreach ($iamUsersWithTrueValue as $iamUser) {
echo "<pre>";
    echo "IAM user '{$iamUser}': true\n" . PHP_EOL;
echo "</pre>";
}
echo "Script completed.\n";
