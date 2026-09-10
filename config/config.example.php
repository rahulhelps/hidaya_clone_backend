<?php
// config/config.example.php
// Copy this file to config/config.php and fill in real values.
// Do NOT commit config.php to version control.

// --- Environment ---
// Set to 'production' before deploying to enable SSL verification.
// Leave as 'local' for local development.
define('APP_ENV', 'local');

// --- BDApps Credentials ---
define('BDAPPS_APP_ID', 'YOUR_BDAPPS_APP_ID');
define('BDAPPS_APP_PASSWORD', 'YOUR_BDAPPS_PASSWORD');

// --- JWT Secret ---
// Generate a random 64-character string, e.g.:
//   openssl rand -hex 32
define('JWT_SECRET', 'GENERATE_A_RANDOM_64_CHAR_STRING');

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'YOUR_DB_NAME');
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASSWORD');

// --- PDO Connection (prepared statements enforced) ---
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'statusCode' => 500,
        'message' => 'Database connection failed',
        'data' => null,
        'timestamp' => date('c')
    ]);
    exit;
}

// --- CORS Headers Helper ---
function sendCorsHeaders(): void
{
    // Allow any origin (restrict to your frontend domain in production)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-Age: 86400');
}

// --- JSON Response Envelope ---
function jsonResponse(bool $success, int $statusCode, string $message, $data = null): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'statusCode' => $statusCode,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit;
}

// --- Phone Number Normalization ---
// Accepts: 018xxxxxxxx, 88018xxxxxxxx, 8818xxxxxxxx
// Normalizes to: 018xxxxxxxx (11 digits, starts with 01[3-9])
function normalizePhoneNumber(string $phone): string
{
    // Strip all non-digit characters
    $digits = preg_replace('/\D/', '', $phone);

    // Handle international formats: 88018... or 8818... -> replace leading 88 with 0
    if (str_starts_with($digits, '88')) {
        $digits = '0' . substr($digits, 2);
    }

    // Validate against regex: ^01[3-9][0-9]{8}$
    if (!preg_match('/^01[3-9][0-9]{8}$/', $digits)) {
        throw new InvalidArgumentException('Invalid phone number format');
    }

    return $digits;
}

// Convert normalized phone (018xxxxxxxx) to BDApps format (tel:88018xxxxxxxx)
function toBdappsPhone(string $normalizedPhone): string
{
    return 'tel:880' . substr($normalizedPhone, 1);
}
