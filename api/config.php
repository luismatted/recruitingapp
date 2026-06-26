<?php
/**
 * Crossing Education - Unified API Config
 * Base file included by all API endpoints
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'u678696734_crossing1');
define('DB_USER', 'u678696734_Luiscrossing1');
define('DB_PASS', 'Juanjito$25');
define('DEFAULT_HASH', '1396232173'); // hash for "talent2025"

/**
 * Get database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    return $pdo;
}

/**
 * Get stored password hash from database
 */
function getStoredHash(): string {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT value FROM settings WHERE `key`='pw_hash' LIMIT 1");
        $row = $stmt->fetch();
        return ($row && $row['value']) ? (string)$row['value'] : DEFAULT_HASH;
    } catch (Exception $e) {
        return DEFAULT_HASH;
    }
}

/**
 * Check if auth parameter is valid
 * Can be via ?auth=HASH query param
 */
function requireAuth(): void {
    $auth = $_GET['auth'] ?? '';
    if ($auth !== getStoredHash()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
}

/**
 * Get JSON request body
 */
function getJsonBody(): array {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}

/**
 * Send success JSON response
 */
function jsonOk($data): void {
    echo json_encode($data);
    exit;
}

/**
 * Send error JSON response
 */
function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

/**
 * Normalize data for API response
 * Converts snake_case DB columns to camelCase for JS frontend
 */
function normalizeKeys(array $row): array {
    $map = [
        'current_role' => 'currentRole',
        'current_company' => 'currentCompany',
        'cv_text' => 'cvText',
        'linked_job_id' => 'linkedJobId',
        'screening_score' => 'screeningScore',
        'screening_verdict' => 'screeningVerdict',
        'screening_rationale' => 'screeningRationale',
        'screening_key_strength' => 'screeningKeyStrength',
        'parsed_data' => 'parsedData',
        'source_job_id' => 'sourceJobId',
        'source_candidate_id' => 'sourceCandidateId',
        'job_type' => 'jobType',
        'created_at' => 'createdAt',
        'updated_at' => 'updatedAt',
        'candidate_id' => 'candidateId',
        'job_id' => 'jobId',
        'key_strength' => 'keyStrength',
        'display_order' => 'displayOrder',
    ];
    
    $result = [];
    foreach ($row as $key => $value) {
        $newKey = $map[$key] ?? $key;
        $result[$newKey] = $value;
    }
    return $result;
}

/**
 * Normalize all rows in a result set
 */
function normalizeRows(array $rows): array {
    return array_map('normalizeKeys', $rows);
}

/**
 * Get raw body (for non-JSON requests)
 */
function getRawBody(): string {
    return file_get_contents('php://input');
}
