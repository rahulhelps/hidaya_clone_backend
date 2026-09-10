<?php
// helpers/jwt_helper.php
// JWT token generation and verification using firebase/php-jwt

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Generate JWT access token (1 hour expiry)
 */
function generateAccessToken(int $userId, string $phoneNumber): string
{
    $payload = [
        'sub' => $userId,
        'phone' => $phoneNumber,
        'type' => 'access',
        'iat' => time(),
        'exp' => time() + (1 * 60 * 60), // 1 hour
    ];

    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

/**
 * Generate refresh token (30 days expiry)
 * Returns [rawToken, tokenHash]
 */
function generateRefreshToken(): array
{
    $rawToken = bin2hex(random_bytes(32)); // 64-char hex string
    $tokenHash = hash('sha256', $rawToken);

    return [$rawToken, $tokenHash];
}

/**
 * Verify and decode access token
 * Returns decoded payload array or null if invalid/expired
 */
function verifyAccessToken(string $token): ?array
{
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return (array) $decoded;
    } catch (ExpiredException $e) {
        return null;
    } catch (SignatureInvalidException $e) {
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Extract Bearer token from Authorization header
 */
function getBearerToken(): ?string
{
    $headers = null;

    // Apache/NGINX server variables
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('getallheaders')) {
        $allHeaders = getallheaders();
        if (isset($allHeaders['Authorization'])) {
            $headers = trim($allHeaders['Authorization']);
        } elseif (isset($allHeaders['authorization'])) {
            $headers = trim($allHeaders['authorization']);
        }
    }

    if ($headers && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }

    return null;
}
