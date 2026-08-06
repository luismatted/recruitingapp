<?php
$allowedOrigins = [
    'https://werecruit4you.pro',
    'https://www.werecruit4you.pro'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://werecruit4you.pro');
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Email');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Correct path to .env
$envPath = '/home/u678696734/domains/werecruit4you.pro/.env';
$apiKey = '';
$envVars = [];

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value);
        }
    }
    $apiKey = $envVars['OPENAI_API_KEY'] ?? '';
}

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

// Database path
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
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Create tables
$db->exec("
    CREATE TABLE IF NOT EXISTS jobs (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        summary TEXT,
        category TEXT,
        jd TEXT,
        location TEXT,
        salary TEXT,
        start_date TEXT,
        hiring_manager TEXT,
        department TEXT,
        employment_type TEXT,
        skills TEXT,
        stage TEXT DEFAULT 'planning',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS candidates (
        id TEXT PRIMARY KEY,
        job_id TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT,
        linkedin TEXT,
        nationality TEXT,
        location TEXT,
        match_score INTEGER DEFAULT 0,
        full_text TEXT,
        ai_notes TEXT,
        admin_notes TEXT,
        status TEXT DEFAULT 'new',
        skills TEXT,
        current_job_title TEXT,
        short_description TEXT,
        cv TEXT,
        gender TEXT,
        age_range TEXT,
        region TEXT,
        seniority TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id)
    )
");

// Landing page lead tables (public uploads)
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

// Migrate: add columns if they don't exist
$migrations = [
    'jobs' => [
        'created_by' => 'TEXT'
    ],
    'candidates' => [
        'admin_notes' => 'TEXT',
        'skills' => 'TEXT',
        'current_job_title' => 'TEXT',
        'short_description' => 'TEXT',
        'cv' => 'TEXT',
        'gender' => 'TEXT',
        'age_range' => 'TEXT',
        'region' => 'TEXT',
        'seniority' => 'TEXT'
    ]
];

foreach ($migrations as $table => $columns) {
    foreach ($columns as $column => $type) {
        try {
            $db->query("SELECT {$column} FROM {$table} LIMIT 1");
        } catch (PDOException $e) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
        }
    }
}

function callOpenAI($apiKey, $prompt, $systemPrompt = '') {
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt ?: 'You are a recruitment AI assistant. Analyze candidate profiles against job descriptions and return structured JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.3
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return ['error' => 'OpenAI API error: HTTP ' . $httpCode];
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        return ['error' => 'Invalid OpenAI response'];
    }

    $content = json_decode($result['choices'][0]['message']['content'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to parse JSON response'];
    }

    return $content;
}
?>