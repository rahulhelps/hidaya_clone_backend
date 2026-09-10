<?php
// api/v1/hidayah/subscription_status.php
// GET /hidayah/subscription
// Requires header: Authorization: Bearer <accessToken>

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../helpers/jwt_helper.php';

sendCorsHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

// Look up user subscription status
$stmt = $pdo->prepare('SELECT subscription_status FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(false, 401, 'Unauthorized');
}

$active = ($user['subscription_status'] === 'active');

jsonResponse(true, 200, 'Subscription status retrieved', [
    'active' => $active,
]);
