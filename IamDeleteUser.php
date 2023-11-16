<?php

require 'vendor/autoload.php';

use Aws\Iam\IamClient;
use GuzzleHttp\Client;

// Set your AWS credentials and region
$awsCredentials = [
    'region' => 'Your_region',
    'version' => 'latest',
    'credentials' => [
        'key' => 'Your_Access_key',
        'secret' => 'Your_Secerete_key',
    ],
];

// Initialize IAM client
$iamClient = new IamClient($awsCredentials);

// Third-party API endpoint to fetch users
$apiEndpoint = 'https://rsupernova.com/userapi.php';

// Fetch users from the third-party API
$httpClient = new Client();
//$response = $httpClient->get($apiEndpoint);
//$thirdPartyUsers = json_decode($response->getBody(), true);
try {
    $response = $httpClient->get('https://rsupernova.com/userapi.php', [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0',
            // Add any other headers if needed
        ],
    ]);
} catch (\GuzzleHttp\Exception\ClientException $e) {
    // Handle the exception, e.g., log the error or display a message
    $response = $e->getResponse();
    $statusCode = $response->getStatusCode();
    $body = $response->getBody()->getContents();
    echo "Error: $statusCode - $body";
}
$thirdPartyUsers = json_decode($response->getBody(), true);
// List IAM users
$iamUsers = $iamClient->listUsers();
// Match and delete IAM users
foreach ($thirdPartyUsers as $thirdPartyUser) {
//echo "Third-party username: {$thirdPartyUser['username']}\n";
$usernameToMatch = $thirdPartyUser['username'];

    // Check if the IAM user with the same username exists
    $matchingIamUser = null;
    foreach ($iamUsers['Users'] as $iamUser) {
//echo "IAM username: {$iamUser['UserName']}\n";
//exit();
        if ($iamUser['UserName'] === $usernameToMatch) {
            $matchingIamUser = $iamUser;
            break;
        }
    }

    // If a matching IAM user is found, delete it
    if ($matchingIamUser) {
        $iamClient->deleteUser(['UserName' => $matchingIamUser['UserName']]);
        echo "IAM user '{$matchingIamUser['UserName']}' deleted.\n";
    }
}

echo "All Matching Users are deleted. \n";
