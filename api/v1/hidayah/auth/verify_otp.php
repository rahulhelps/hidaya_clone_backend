<?php
// api/v1/hidayah/auth/verify_otp.php
// POST /hidayah/auth/otp/verify
// Request: { "phoneNumber", "referenceNo", "code" }

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

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['phoneNumber']) || empty($input['referenceNo']) || empty($input['code'])) {
    jsonResponse(false, 400, 'phoneNumber, referenceNo, and code are required');
}

// Normalize phone number
try {
    $phoneNumber = normalizePhoneNumber($input['phoneNumber']);
} catch (InvalidArgumentException $e) {
    jsonResponse(false, 400, 'Invalid phone number format');
}

$referenceNo = $input['referenceNo'];
$code = $input['code'];

// Verify OTP with BDApps
$bdappsResponse = bdappsVerifyOtp($referenceNo, $code, $phoneNumber);

if (!$bdappsResponse || !isset($bdappsResponse['statusCode']) || $bdappsResponse['statusCode'] !== 'S1000') {
    $message = 'OTP verification failed';
    if (isset($bdappsResponse['statusDetail'])) {
        $message = $bdappsResponse['statusDetail'];
    }
    jsonResponse(false, 400, $message);
}

// BDApps verify success â€” find or create user
global $pdo;

$stmt = $pdo->prepare('SELECT id, phone_number, role FROM users WHERE phone_number = ? LIMIT 1');
$stmt->execute([$phoneNumber]);
$user = $stmt->fetch();

$isNewUser = false;

if (!$user) {
    // Insert new user
    $isNewUser = true;
    $stmt = $pdo->prepare('INSERT INTO users (phone_number) VALUES (?)');
    $stmt->execute([$phoneNumber]);
    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT id, phone_number, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} else {
    $userId = (int)$user['id'];
}

// Update OTP request status to verified
$stmt = $pdo->prepare("UPDATE otp_requests SET status = 'verified' WHERE reference_no = ? AND phone_number = ?");
$stmt->execute([$referenceNo, $phoneNumber]);

// Generate JWT tokens
$accessToken = generateAccessToken($userId, $user['phone_number']);
list($refreshToken, $tokenHash) = generateRefreshToken();

// Store refresh token
$stmt = $pdo->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))');
$stmt->execute([$userId, $tokenHash]);

jsonResponse(true, 200, 'OTP verified successfully', [
    'user' => [
        'id' => $userId,
        'phoneNumber' => $user['phone_number'],
        'role' => $user['role'],
    ],
    'accessToken' => $accessToken,
    'refreshToken' => $refreshToken,
    'isNewUser' => $isNewUser,
]);

