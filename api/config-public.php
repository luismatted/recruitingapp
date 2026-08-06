<?php
// Public-safe config for landing page endpoints.
// No CORS wildcard, no admin auth — read-only matching + lead capture only.
header('Content-Type: application/json');

// .env
$envPath = '/home/u678696734/domains/werecruit4you.pro/.env';
$apiKey = '';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            if (trim($key) === 'OPENAI_API_KEY') {
                $apiKey = trim($value);
            }
        }
    }
}

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'Service not configured']);
    exit;
}

// Database
$dbDir = '/home/u678696734/domains/werecruit4you.pro/data';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}
$dbPath = $dbDir . '/database.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Landing tables (idempotent — safe to run on every request)
$db->exec("
    CREATE TABLE IF NOT EXISTS talent_pool (
        id TEXT PRIMARY KEY,
        name TEXT,
        email TEXT,
        linkedin TEXT,
        location TEXT,
        current_job_title TEXT,
        skills TEXT,
        seniority TEXT,
        short_description TEXT,
        full_text TEXT,
        best_match_job_id TEXT,
        best_match_score INTEGER DEFAULT 0,
        match_results TEXT,
        script_answers TEXT,
        source TEXT DEFAULT 'landing',
        status TEXT DEFAULT 'new',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS landing_jds (
        id TEXT PRIMARY KEY,
        company_name TEXT,
        contact_name TEXT,
        email TEXT,
        title TEXT,
        summary TEXT,
        category TEXT,
        jd TEXT,
        location TEXT,
        salary TEXT,
        employment_type TEXT,
        skills TEXT,
        best_match_candidate_id TEXT,
        best_match_score INTEGER DEFAULT 0,
        match_results TEXT,
        script_answers TEXT,
        source TEXT DEFAULT 'landing',
        status TEXT DEFAULT 'new',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS rate_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT,
        endpoint TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Rate limit: max 20 requests per hour per IP across public endpoints
function checkRateLimit($db, $endpoint) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // housekeeping: drop entries older than 24h
    $db->exec("DELETE FROM rate_log WHERE created_at < datetime('now', '-1 day')");

    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM rate_log WHERE ip = ? AND created_at > datetime('now', '-1 hour')");
    $stmt->execute([$ip]);
    $count = (int)$stmt->fetch()['c'];

    if ($count >= 20) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please try again later or contact us at contact@werecruit4you.pro']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO rate_log (ip, endpoint) VALUES (?, ?)");
    $stmt->execute([$ip, $endpoint]);
}

// Shared AI caller
function callAI($apiKey, $prompt, $systemPrompt = '', $jsonMode = true) {
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt ?: 'You are a recruitment AI assistant. Return structured JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3
    ];
    if ($jsonMode) {
        $data['response_format'] = ['type' => 'json_object'];
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return ['error' => 'AI service error'];
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        return ['error' => 'Invalid AI response'];
    }

    if (!$jsonMode) {
        return ['text' => $result['choices'][0]['message']['content']];
    }

    $content = json_decode($result['choices'][0]['message']['content'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to parse AI response'];
    }

    return $content;
}
?>
