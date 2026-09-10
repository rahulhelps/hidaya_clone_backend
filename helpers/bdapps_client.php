<?php
// helpers/bdapps_client.php
// Reusable BDApps carrier API client with request/response logging

require_once __DIR__ . '/../config/config.php';

/**
 * Log BDApps raw request/response to logs/ folder
 * Passwords are redacted before writing to disk.
 */
function logBdapps(string $endpoint, array $requestPayload, ?array $responsePayload, ?string $error = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $date = date('Y-m-d');
    $logFile = $logDir . '/bdapps_' . $date . '.log';

    $sanitizedRequest = $requestPayload;
    if (isset($sanitizedRequest['password'])) {
        $sanitizedRequest['password'] = '***REDACTED***';
    }

    $entry = [
        'timestamp' => date('c'),
        'endpoint' => $endpoint,
        'request' => $sanitizedRequest,
        'response' => $responsePayload,
        'error' => $error,
    ];

    file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

/**
 * Make a cURL request to BDApps API
 */
function bdappsRequest(string $url, array $payload): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    if (defined('APP_ENV') && APP_ENV === 'production') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => $curlError, 'http_code' => $httpCode];
    }

    $decoded = json_decode($responseBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response', 'http_code' => $httpCode, 'raw' => $responseBody];
    }

    return $decoded;
}

/**
 * Send OTP via BDApps
 */
function bdappsSendOtp(string $phoneNumber): ?array
{
    $url = 'https://developer.bdapps.com/subscription/otp/request';
    $payload = [
        'applicationId' => BDAPPS_APP_ID,
        'password' => BDAPPS_APP_PASSWORD,
        'subscriberId' => toBdappsPhone($phoneNumber),
        'applicationHash' => '',
        'applicationMetaData' => 'Hidayah App',
    ];

    $response = bdappsRequest($url, $payload);
    logBdapps('otp/request', $payload, $response);

    return $response;
}

/**
 * Verify OTP via BDApps
 */
function bdappsVerifyOtp(string $referenceNo, string $otp, string $phoneNumber): ?array
{
    $url = 'https://developer.bdapps.com/subscription/otp/verify';
    $payload = [
        'applicationId' => BDAPPS_APP_ID,
        'password' => BDAPPS_APP_PASSWORD,
        'referenceNo' => $referenceNo,
        'otp' => $otp,
    ];

    $response = bdappsRequest($url, $payload);
    logBdapps('otp/verify', $payload, $response);

    return $response;
}

/**
 * Unsubscribe user via BDApps
 */
function bdappsUnsubscribe(string $phoneNumber): ?array
{
    $url = 'https://developer.bdapps.com/subscription/send';
    $payload = [
        'applicationId' => BDAPPS_APP_ID,
        'password' => BDAPPS_APP_PASSWORD,
        'subscriberId' => toBdappsPhone($phoneNumber),
        'version' => '1.0',
        'action' => '0',
    ];

    $response = bdappsRequest($url, $payload);
    logBdapps('unsubscribe', $payload, $response);

    return $response;
}

/**
 * Check subscription status via BDApps
 */
function bdappsGetSubscriptionStatus(string $phoneNumber): ?array
{
    $url = 'https://developer.bdapps.com/subscription/getStatus';
    $payload = [
        'version' => '1.0',
        'applicationId' => BDAPPS_APP_ID,
        'password' => BDAPPS_APP_PASSWORD,
        'subscriberId' => toBdappsPhone($phoneNumber),
    ];

    $response = bdappsRequest($url, $payload);
    logBdapps('getStatus', $payload, $response);

    return $response;
}


