<?php
// api/v1/hidayah/auth/unsubscribe.php
// POST /hidayah/auth/unsubscribe
// Requires header: Authorization: Bearer <accessToken>

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../helpers/jwt_helper.php';
require_once __DIR__ . '/../../../../helpers/bdapps_client.php';

sendCorsHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 405, 'Method not allowed');
}

// Validate JWT
$accessToken = getBearerToken();
if (!$accessToken) {
    jsonResponse(false, 401, 'Unauthorized');
}

$payload = verifyAccessToken($accessToken);
if (!$payload || !isset($payload['sub'])) {
    jsonResponse(false, 401, 'Unauthorized');
}

$userId = (int)$payload['sub'];

global $pdo;

// Look up user
$stmt = $pdo->prepare('SELECT id, phone_number, subscription_status FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(false, 401, 'Unauthorized');
}

$phoneNumber = $user['phone_number'];
$alreadyUnsubscribed = ($user['subscription_status'] === 'unsubscribed');

if ($alreadyUnsubscribed) {
    jsonResponse(true, 200, 'Already unsubscribed', [
        'alreadyUnsubscribed' => true,
    ]);
}

// Call BDApps unsubscribe
$bdappsResponse = bdappsUnsubscribe($phoneNumber);

$unsubscribeSuccess = false;

if ($bdappsResponse) {
    // Success if statusCode is S1000 or subscriptionStatus is UNREGISTERED
    $statusCode = $bdappsResponse['statusCode'] ?? '';
    $subscriptionStatus = $bdappsResponse['subscriptionStatus'] ?? '';

    if ($statusCode === 'S1000' || strtoupper($subscriptionStatus) === 'UNREGISTERED') {
        $unsubscribeSuccess = true;
    }
}

if (!$unsubscribeSuccess) {
    $message = 'Failed to unsubscribe';
    if (isset($bdappsResponse['statusDetail'])) {
        $message = $bdappsResponse['statusDetail'];
    }
    jsonResponse(false, 500, $message);
}

// Update local DB
$stmt = $pdo->prepare("UPDATE users SET subscription_status = 'unsubscribed' WHERE id = ?");
$stmt->execute([$userId]);

jsonResponse(true, 200, 'Unsubscribed successfully', [
    'alreadyUnsubscribed' => false,
]);

