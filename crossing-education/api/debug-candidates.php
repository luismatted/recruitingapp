<?php
/**
 * Debug script for candidates table
 * Upload, visit once, then delete
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'u678696734_crossing1';
$user = 'u678696734_Luiscrossing1';
$pass = 'Juanjito$25';

header('Content-Type: text/html; charset=utf-8');

echo '<style>body{font-family:monospace;padding:24px;background:#0f172a;color:#e2e8f0;line-height:1.6} .ok{color:#4ade80} .err{color:#f87171} code{background:#1e293b;padding:2px 6px;border-radius:4px}</style>';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<div class="ok">Connected</div>';

    // Show columns
    echo '<h3>Columns in candidates table:</h3>';
    $cols = $pdo->query("SHOW COLUMNS FROM candidates")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo '<div><code>' . $c['Field'] . '</code> | ' . $c['Type'] . ' | Null: ' . $c['Null'] . '</div>';
    }

    // Test 1: Simplest possible insert
    echo '<h3>Test 1: Minimal INSERT</h3>';
    try {
        $pdo->prepare("INSERT INTO candidates (name) VALUES (?)")->execute(['Test User']);
        $id = $pdo->lastInsertId();
        echo '<div class="ok">SUCCESS! ID=' . $id . '</div>';
        // Clean up
        $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
        echo '<div class="ok">Cleaned up test row</div>';
    } catch (PDOException $e) {
        echo '<div class="err">FAILED: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    // Test 2: Full insert matching our API
    echo '<h3>Test 2: Full INSERT (matching API)</h3>';
    try {
        $pdo->prepare("INSERT INTO candidates (name, current_role, current_company, location, cv_text, linked_job_id, screening_score, screening_verdict, screening_rationale, screening_key_strength, stage, parsed_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute(['Test User 2', 'Role', 'Company', 'Location', 'CV text', null, null, null, null, null, 'PENDING', '{}']);
        $id = $pdo->lastInsertId();
        echo '<div class="ok">SUCCESS! ID=' . $id . '</div>';
        $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
        echo '<div class="ok">Cleaned up test row</div>';
    } catch (PDOException $e) {
        echo '<div class="err">FAILED: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<div class="err">Error code: ' . $e->getCode() . '</div>';
    }

    // Test 3: With linked_job_id = 2147483648
    echo '<h3>Test 3: INSERT with big job ID</h3>';
    try {
        $pdo->prepare("INSERT INTO candidates (name, linked_job_id) VALUES (?, ?)")
            ->execute(['Test User 3', 2147483648]);
        $id = $pdo->lastInsertId();
        echo '<div class="ok">SUCCESS! ID=' . $id . '</div>';
        $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
    } catch (PDOException $e) {
        echo '<div class="err">FAILED: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    echo '<div style="margin-top:24px" class="err"><strong>Delete this file after testing!</strong></div>';

} catch (PDOException $e) {
    echo '<div class="err">Connection error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
