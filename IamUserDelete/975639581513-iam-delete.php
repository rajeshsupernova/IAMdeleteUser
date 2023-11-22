<?php

include('config.php');
require 'vendor/autoload.php';

use Aws\Credentials\Credentials;
use Aws\Iam\IamClient;
use GuzzleHttp\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Authenticate with third-party API to get access token
$client = new Client();
$response = $client->post($apiConfig['api_url'], [
    'json' => [
        'grant_type' => 'client_credentials',
        'client_id' => $apiConfig['client_id'],
        'client_secret' => $apiConfig['client_secret'],
    ],
]);

$data = json_decode($response->getBody(), true);
$accessToken = $data['access_token'];

// Use AWS SDK to interact with IAM
$iamClient = new IamClient($awsConfig1);

// Get list of IAM users
$iamUsers = $iamClient->listUsers();

// Call the third-party API to get the list of users
$apiUsersResponse = $client->get($apiConfig['api_url_getEmail'], [
    'headers' => [
        'Authorization' => 'Bearer ' . $accessToken,
    ],
]);

$apiUsers = json_decode($apiUsersResponse->getBody(), true);

// Extract email addresses from the third-party API data
$apiUserEmails = $apiUsers['data'];

// Initialize a variable to store matched IAM users
$matchedIAMUsers = [];

// Match and collect users in IAM (case-insensitive)
foreach ($apiUserEmails as $apiUserEmail) {
    // Compare email addresses with IAM user names (case-insensitive)
    foreach ($iamUsers['Users'] as $iamUser) {
        if (isset($iamUser['UserName']) && strtolower($apiUserEmail) === strtolower($iamUser['UserName'])) {
            
			// Match found, store IAM user
            $matchedIAMUsers[] = $iamUser['UserName'];
			
			// Match found, output IAM user
                        echo "<pre>";
            echo 'Matched IAM User: ' . $iamUser['UserName'] . PHP_EOL;
                        echo "</pre>";
			
            // Detach policies from the IAM user
            $attachedPolicies = $iamClient->listAttachedUserPolicies(['UserName' => $iamUser['UserName']]);
            foreach ($attachedPolicies['AttachedPolicies'] as $policy) {
                $iamClient->detachUserPolicy([
                    'UserName' => $iamUser['UserName'],
                    'PolicyArn' => $policy['PolicyArn'],
                ]);
            }

            // Detach inline policies from the IAM user
            $userPolicies = $iamClient->listUserPolicies(['UserName' => $iamUser['UserName']]);
            foreach ($userPolicies['PolicyNames'] as $policyName) {
                $iamClient->deleteUserPolicy([
                    'UserName' => $iamUser['UserName'],
                    'PolicyName' => $policyName,
                ]);
            }

            // Delete the IAM user
            $iamClient->deleteUser(['UserName' => $iamUser['UserName']]);


// Uncomment Below lines to test getting the right match
/*
			$result = $iamClient->getUser(array(
			'UserName' => $iamUser['UserName'],
		));
		var_dump($result);
*/
                        echo "<pre>";
            echo 'Deleted IAM User: ' . $iamUser['UserName'] . PHP_EOL;
                        echo "</pre>";
        }
    }
}

// If matches are found, send an email using PHPMailer
if (!empty($matchedIAMUsers)) {
    $emailContent = '<html><body>';
    $emailContent .= '<p>Deleted IAM Users(Exited Employee) from MFCEBIZ AWS Account:</p>';
    $emailContent .= '<ul>';
    foreach ($matchedIAMUsers as $matchedUser) {
        $emailContent .= '<li>' . $matchedUser . '</li>';
    }
    $emailContent .= '</ul>';
    $emailContent .= '</body></html>';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $emailConfig['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['smtp']['username'];
        $mail->Password   = $emailConfig['smtp']['password'];
        $mail->SMTPSecure = $emailConfig['smtp']['encryption'];
        $mail->Port       = $emailConfig['smtp']['port'];

        $mail->setFrom($emailConfig['from']);
        $mail->addAddress($emailConfig['to']);
        $mail->isHTML(true);
        $mail->Subject = $emailConfig['subject'];
        $mail->Body    = $emailContent;

        $mail->send();
        echo 'Email sent with the list of matched IAM users.';
    } catch (Exception $e) {
        echo 'Email could not be sent. Error: ', $mail->ErrorInfo;
    }
} else {
    echo 'No matches found.';
}
?>