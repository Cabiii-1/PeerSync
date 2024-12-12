<?php
session_start();
include 'config.php';

$user_id = $_SESSION['user_id'];



// Check if the user has an access token
if (!isset($_SESSION['access_token'])) {
    // Check if we have an authorization code
    if (isset($_GET['code'])) {
        // Exchange the authorization code for an access token
        $token_url = 'https://oauth2.googleapis.com/token';
        $post_data = [
            'code' => $_GET['code'],
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            echo "CURL Error during token exchange: " . $curl_error . "<br>";
            exit;
        }

        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            $_SESSION['access_token'] = $data['access_token'];
            echo "Successfully obtained access token<br>";
        } else {
            echo "Error getting access token. Response:<br>";
            echo "<pre>" . print_r($data, true) . "</pre>";
            exit;
        }
    } else {
        // Redirect to the authorization URL with proper encoding
        $auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => $scope,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);
        
        header("Location: " . $auth_url);
        exit();
    }
}

// Set timezone to Asia/Singapore
date_default_timezone_set('Asia/Singapore');

// Create a new Google Meet event
$event = [
    'summary' => 'Meeting Title',
    'start' => [
        'dateTime' => date('c'), // Current time
        'timeZone' => 'Asia/Singapore',
    ],
    'end' => [
        'dateTime' => date('c', strtotime('+1 hour')), // 1 hour from now
        'timeZone' => 'Asia/Singapore',
    ],
    'conferenceData' => [
        'createRequest' => [
            'requestId' => uniqid(), // Unique request ID
            'conferenceSolutionKey' => [
                'type' => 'hangoutsMeet',
            ],
        ],
    ],
];

$url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1';
$headers = [
    'Authorization: Bearer ' . $_SESSION['access_token'],
    'Content-Type: application/json',
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));

$response = curl_exec($ch);
curl_close($ch);

// Check the response
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['htmlLink'])) {
        echo 'Meeting created: <a href="' . $data['htmlLink'] . '" target="_blank">' . $data['htmlLink'] . '</a>'; // Link to the Google Meet
    } else {
        echo 'Error creating meeting: ' . print_r($data, true);
    }
} else {
    echo 'Error creating meeting: ' . curl_error($ch);
}
?>