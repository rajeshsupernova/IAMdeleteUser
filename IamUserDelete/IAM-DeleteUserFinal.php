<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
include ('config.php');
require 'vendor/autoload.php';
use Aws\Credentials\Credentials;
use Aws\Iam\IamClient;
use GuzzleHttp\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Authenticate with third-party API to get access token
$client = new Client();
$response = $client->post($apiConfig['api_url'], ['json' => ['grant_type' => 'client_credentials', 'client_id' => $apiConfig['client_id'], 'client_secret' => $apiConfig['client_secret'], ], ]);
$data = json_decode($response->getBody(), true);
$accessToken = $data['access_token'];
// Use AWS SDK to interact with IAM
$iamClient = new IamClient($awsConfig1);
// Get list of IAM users
$iamUsers = $iamClient->listUsers();
// Call the third-party API to get the list of users
$apiUsersResponse = $client->get($apiConfig['api_url_getEmail'], ['headers' => ['Authorization' => 'Bearer ' . $accessToken, ], ]);
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
            try {
                // Detach policies from the IAM user
                $attachedPolicies = $iamClient->listAttachedUserPolicies(['UserName' => $iamUser['UserName']]);
                foreach ($attachedPolicies['AttachedPolicies'] as $policy) {
                    $iamClient->detachUserPolicy(['UserName' => $iamUser['UserName'], 'PolicyArn' => $policy['PolicyArn'], ]);
                }
                // Detach inline policies from the IAM user
                $userPolicies = $iamClient->listUserPolicies(['UserName' => $iamUser['UserName']]);
                foreach ($userPolicies['PolicyNames'] as $policyName) {
                    $iamClient->deleteUserPolicy(['UserName' => $iamUser['UserName'], 'PolicyName' => $policyName, ]);
                }
                // List groups for the user
                $groups = $iamClient->listGroupsForUser(['UserName' => $iamUser['UserName'], ]);
                // Remove the user from all groups
                foreach ($groups['Groups'] as $group) {
                    $groupName = $group['GroupName'];
                    try {
                        $iamClient->removeUserFromGroup(['GroupName' => $groupName, 'UserName' => $iamUser['UserName'], ]);
                        echo "User username removed from group $groupName successfully.\n";
                    }
                    catch(\Aws\Exception\AwsException $e) {
                        // Handle the case where the user might not be a member of the group
                        echo "Error removing user from group: " . $e->getMessage() . "\n";
                    }
                }
                // Check if the user has access keys
                $accessKeys = $iamClient->listAccessKeys(['UserName' => $iamUser['UserName'], ]);
                // Delete access keys if they exist
                foreach ($accessKeys['AccessKeyMetadata'] as $accessKey) {
                    try {
                        $iamClient->deleteAccessKey(['UserName' => $iamUser['UserName'], 'AccessKeyId' => $accessKey['AccessKeyId'], ]);
                        echo "Access key {$accessKey['AccessKeyId']} for user username deleted successfully.\n";
                    }
                    catch(\Aws\Exception\AwsException $e) {
                        // Handle the case where the access key might not exist
                        echo "Error deleting access key: " . $e->getMessage() . "\n";
                    }
                }
                // Check if the user has a login profile
                try {
                    $loginProfile = $iamClient->getLoginProfile(['UserName' => $iamUser['UserName'], ]);
                    // If a login profile exists, delete it
                    $iamClient->deleteLoginProfile(['UserName' => $iamUser['UserName'], ]);
                    echo "Login profile for user deleted successfully.\n";
                }
                catch(\Aws\Exception\AwsException $e) {
                    // Handle the case where the login profile might not exist
                    echo "Error deleting login profile: " . $e->getMessage() . "\n";
                }
                // List MFA devices for the user
                $listMFA = $iamClient->listMFADevices(['UserName' => $iamUser['UserName'], ]);
                // Deactivate and delete each MFA device
                foreach ($listMFA['MFADevices'] as $mfaDevice) {
                    $serialNumber = $mfaDevice['SerialNumber'];
                    try {
                        $iamClient->deactivateMFADevice(['UserName' => $iamUser['UserName'], 'SerialNumber' => $serialNumber, ]);
                        echo "MFA device with serial number $serialNumber deactivated successfully.\n";
                    }
                    catch(\Aws\Exception\AwsException $e) {
                        // Handle the case where the MFA device might not exist
                        echo "Error deactivating MFA device: " . $e->getMessage() . "\n";
                    }
                }
            }
            catch(Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
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
    $emailContent.= '<p>Deleted IAM Users(Exited Employee) from AWS Account: ' . $awsConfig1['account'] . '</p>';
    $emailContent.= '<ul>';
    foreach ($matchedIAMUsers as $matchedUser) {
        $emailContent.= '<li>' . $matchedUser . '</li>';
    }
    $emailContent.= '</ul>';
    $emailContent.= '</body></html>';
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $emailConfig['smtp']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['smtp']['username'];
        $mail->Password = $emailConfig['smtp']['password'];
        $mail->SMTPSecure = $emailConfig['smtp']['encryption'];
        $mail->Port = $emailConfig['smtp']['port'];
        $mail->setFrom($emailConfig['from']);
        $mail->addAddress($emailConfig['to']);
        $mail->isHTML(true);
        $mail->Subject = $emailConfig['subject'];
        $mail->Body = $emailContent;
        $mail->send();
        echo 'Email sent with the list of matched IAM users.';
    }
    catch(Exception $e) {
        echo 'Email could not be sent. Error: ', $mail->ErrorInfo;
    }
} else {
    echo 'No matches found.';
}
?>