<?php
// api/v1/hidayah/auth/refresh_token.php
// POST /auth/refresh
// Request: { "refreshToken": "raw_token_string" }

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../helpers/jwt_helper.php';

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
if (!is_array($input) || empty($input['refreshToken'])) {
    jsonResponse(false, 400, 'refreshToken is required');
}

$rawToken = $input['refreshToken'];
$tokenHash = hash('sha256', $rawToken);

global $pdo;

// Look up refresh token
$stmt = $pdo->prepare('
    SELECT rt.id, rt.user_id, rt.expires_at, rt.revoked, u.phone_number
    FROM refresh_tokens rt
    JOIN users u ON rt.user_id = u.id
    WHERE rt.token_hash = ?
    LIMIT 1
');
$stmt->execute([$tokenHash]);
$tokenRecord = $stmt->fetch();

if (!$tokenRecord) {
    jsonResponse(false, 401, 'Invalid refresh token');
}

$tokenId = (int)$tokenRecord['id'];
$userId = (int)$tokenRecord['user_id'];
$expiresAt = $tokenRecord['expires_at'];
$revoked = (bool)$tokenRecord['revoked'];

// Check if expired
if (new DateTime() > new DateTime($expiresAt)) {
    jsonResponse(false, 401, 'Refresh token expired');
}

// Check if revoked (reuse detection)
if ($revoked) {
    jsonResponse(false, 401, 'Refresh token has been revoked');
}

// Invalidate old refresh token (rotate)
$stmt = $pdo->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE id = ?');
$stmt->execute([$tokenId]);

// Issue new tokens
$accessToken = generateAccessToken($userId, $tokenRecord['phone_number']);
list($newRefreshToken, $newTokenHash) = generateRefreshToken();

// Store new refresh token
$stmt = $pdo->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))');
$stmt->execute([$userId, $newTokenHash]);

jsonResponse(true, 200, 'Tokens refreshed', [
    'accessToken' => $accessToken,
    'refreshToken' => $newRefreshToken,
    'user' => [
        'id' => $userId,
        'phoneNumber' => $tokenRecord['phone_number'],
    ],
]);

