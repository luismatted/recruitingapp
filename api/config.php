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

define('DB_PATH', __DIR__ . '/../database.sqlite');
define('DEFAULT_HASH', '1396232173'); // hash for "talent2025"

/**
 * Get database connection (singleton) — SQLite
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'sqlite:' . DB_PATH,
            null, null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA foreign_keys=ON;');
        initSchema($pdo);
    }
    return $pdo;
}

/**
 * Create tables if they don't exist
 */
function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `key`   TEXT PRIMARY KEY,
            `value` TEXT
        );

        CREATE TABLE IF NOT EXISTS jobs (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            title         TEXT NOT NULL,
            company       TEXT DEFAULT '',
            location      TEXT DEFAULT '',
            salary        TEXT DEFAULT '',
            description   TEXT DEFAULT '',
            requirements  TEXT DEFAULT '',
            skills        TEXT DEFAULT '[]',
            status        TEXT DEFAULT 'active',
            display_order INTEGER DEFAULT 0,
            parsed_data   TEXT DEFAULT '{}',
            created_at    TEXT DEFAULT (datetime('now')),
            updated_at    TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS candidates (
            id                    INTEGER PRIMARY KEY AUTOINCREMENT,
            name                  TEXT NOT NULL,
            current_role          TEXT DEFAULT '',
            current_company       TEXT DEFAULT '',
            location              TEXT DEFAULT '',
            email                 TEXT DEFAULT '',
            phone                 TEXT DEFAULT '',
            linked_in             TEXT DEFAULT '',
            cv_text               TEXT DEFAULT '',
            seniority             TEXT DEFAULT '',
            skills                TEXT DEFAULT '[]',
            linked_job_id         INTEGER DEFAULT NULL,
            screening_score       INTEGER DEFAULT NULL,
            screening_verdict     TEXT DEFAULT NULL,
            screening_rationale   TEXT DEFAULT NULL,
            screening_key_strength TEXT DEFAULT NULL,
            stage                 TEXT DEFAULT 'PENDING',
            parsed_data           TEXT DEFAULT '{}',
            created_at            TEXT DEFAULT (datetime('now')),
            updated_at            TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS screenings (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            candidate_id INTEGER NOT NULL,
            job_id       INTEGER NOT NULL,
            verdict      TEXT DEFAULT 'MAYBE',
            score        INTEGER DEFAULT 50,
            rationale    TEXT DEFAULT '',
            key_strength TEXT DEFAULT '',
            created_at   TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS public_jobs (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            source_job_id INTEGER DEFAULT NULL,
            title         TEXT NOT NULL,
            company       TEXT DEFAULT '',
            location      TEXT DEFAULT '',
            salary        TEXT DEFAULT '',
            job_type      TEXT DEFAULT '',
            description   TEXT DEFAULT '',
            skills        TEXT DEFAULT '[]',
            created_at    TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS public_candidates (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            source_candidate_id INTEGER DEFAULT NULL,
            name                TEXT NOT NULL,
            role                TEXT DEFAULT '',
            location            TEXT DEFAULT '',
            experience          TEXT DEFAULT '',
            highlight           TEXT DEFAULT '',
            skills              TEXT DEFAULT '[]',
            display_order       INTEGER DEFAULT 0,
            created_at          TEXT DEFAULT (datetime('now'))
        );
    ");
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
    return array_map(function($row) {
        $item = normalizeKeys($row);
        if (isset($item['skills']) && is_string($item['skills'])) {
            $decoded = json_decode($item['skills'], true);
            $item['skills'] = is_array($decoded) ? $decoded : [];
        }
        if (isset($item['parsedData']) && is_string($item['parsedData'])) {
            $decoded = json_decode($item['parsedData'], true);
            $item['parsedData'] = is_array($decoded) ? $decoded : (object)[];
        }
        return $item;
    }, $rows);
}

/**
 * Get raw body (for non-JSON requests)
 */
function getRawBody(): string {
    return file_get_contents('php://input');
}
