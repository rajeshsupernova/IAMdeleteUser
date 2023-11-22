<?php

require 'vendor/autoload.php';
use Aws\Credentials\Credentials;

// Configuration for third-party API
$apiConfig = [
    'api_url' => 'your_api_url_for_auth',
    'api_url_getEmail' => 'your_api_url_to_get_user_list',
    'client_id' => 'api_username',
    'client_secret' => 'api_password',
];

// Configuration for AWS Account1 (IAM-CleanUp-User)
$awsConfig1 = [
    'version' => 'latest',
    'region' => 'ap-south-1',
    'credentials' => new Credentials('XXXXXXXXXXXXXXXXX', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
];

// Configuration for AWS Account2 (IAM-CleanUp-User)
$awsConfig2 = [
    'version' => 'latest',
    'region' => 'ap-south-1',
    'credentials' => new Credentials('XXXXXXXXXXXXXXXXX', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
];

// Email configuration
$emailConfig = [
    'to' => 'info@example.com',
    'subject' => 'IAM User Matches',
    'from' => 'aws@example.com',
    'smtp' => [
        'host' => 'email-smtp.us-east-1.amazonaws.com',
        'port' => 587, // Change to your SMTP port
        'username' => 'XXXXXXXXXXXXXXXXX',
        'password' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'encryption' => 'tls', // Change to 'ssl' if necessary
    ],
];
?>