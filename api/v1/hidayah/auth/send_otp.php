<?php
// api/v1/hidayah/auth/send_otp.php
// POST /hidayah/auth/otp/send
// Request: { "phoneNumber": "01xxxxxxxxx" }

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../helpers/jwt_helper.php';
require_once __DIR__ . '/../../../../helpers/bdapps_client.php';

sendCorsHeaders();
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 405, 'Method not allowed');
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['phoneNumber'])) {
    jsonResponse(false, 400, 'phoneNumber is required');
}

// Normalize phone number
try {
    $phoneNumber = normalizePhoneNumber($input['phoneNumber']);
} catch (InvalidArgumentException $e) {
    jsonResponse(false, 400, 'Invalid phone number format. Expected: 01XXXXXXXXX');
}

global $pdo;

// Check if user already exists with active subscription in our DB
$stmt = $pdo->prepare('SELECT id, phone_number, role FROM users WHERE phone_number = ? AND subscription_status = ? LIMIT 1');
$stmt->execute([$phoneNumber, 'active']);
$existingUser = $stmt->fetch();

if ($existingUser) {
    // User already registered and active â€” issue tokens immediately
    $accessToken = generateAccessToken((int)$existingUser['id'], $existingUser['phone_number']);
    list($refreshToken, $tokenHash) = generateRefreshToken();

    // Store refresh token
    $stmt = $pdo->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))');
    $stmt->execute([$existingUser['id'], $tokenHash]);

    jsonResponse(true, 200, 'User already registered', [
        'alreadyRegistered' => true,
        'user' => [
            'id' => (int)$existingUser['id'],
            'phoneNumber' => $existingUser['phone_number'],
            'role' => $existingUser['role'],
        ],
        'accessToken' => $accessToken,
        'refreshToken' => $refreshToken,
        'isNewUser' => false,
    ]);
}

// New user â€” request OTP from BDApps
$bdappsResponse = bdappsSendOtp($phoneNumber);

if (!$bdappsResponse || !isset($bdappsResponse['referenceNo'])) {
    $message = 'Failed to send OTP';
    if (isset($bdappsResponse['statusDetail'])) {
        $message = $bdappsResponse['statusDetail'];
    }
    jsonResponse(false, 500, $message);
}

// Store OTP request in DB
$referenceNo = $bdappsResponse['referenceNo'];
$expiresAt = date('Y-m-d H:i:s', time() + 60); // 60 seconds from now

$stmt = $pdo->prepare('INSERT INTO otp_requests (phone_number, reference_no, expires_at) VALUES (?, ?, ?)');
$stmt->execute([$phoneNumber, $referenceNo, $expiresAt]);

jsonResponse(true, 200, 'OTP sent successfully', [
    'alreadyRegistered' => false,
    'referenceNo' => $referenceNo,
    'expiresInSeconds' => 60,
]);

