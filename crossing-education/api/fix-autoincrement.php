<?php
/**
 * Fix auto-increment counters for all tables
 * Upload to api/fix-autoincrement.php, visit once, then delete
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'u678696734_crossing1';
$user = 'u678696734_Luiscrossing1';
$pass = 'Juanjito$25';

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head>';
echo '<style>body{font-family:monospace;padding:24px;background:#0f172a;color:#e2e8f0;line-height:1.6} .ok{color:#4ade80} .warn{color:#fbbf24} .err{color:#f87171} code{background:#1e293b;padding:2px 8px;border-radius:4px}</style></head><body>';
echo '<h2>Auto-Increment Fix</h2>';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<div class="ok">Connected</div>';

    $tables = ['jobs', 'candidates', 'screenings', 'public_jobs', 'public_candidates', 'settings'];

    foreach ($tables as $table) {
        echo '<h3>Table: <code>' . $table . '</code></h3>';

        // Check if table exists
        $exists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
        if (!$exists) {
            echo '<div class="warn">Table does not exist, skipping</div>';
            continue;
        }

        // Get current auto-increment value
        $ai = $pdo->query("SHOW TABLE STATUS LIKE '$table'")->fetch(PDO::FETCH_ASSOC);
        $currentAI = $ai['Auto_increment'] ?? 'N/A';
        echo '<div>Current auto-increment: <code>' . $currentAI . '</code></div>';

        // Get max id
        $maxRow = $pdo->query("SELECT MAX(id) as max_id FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        $maxId = $maxRow['max_id'] ?? 0;
        echo '<div>Max existing ID: <code>' . ($maxId ?: 'none') . '</code></div>';

        // Determine new auto-increment
        $newAI = $maxId ? ($maxId + 1) : 1;

        // Check if current AI is corrupted (too high)
        if ($currentAI === 'N/A' || $currentAI > 1000000000 || $currentAI < $newAI) {
            $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = $newAI");
            echo '<div class="ok">Fixed! Set AUTO_INCREMENT to ' . $newAI . '</div>';
        } else {
            echo '<div class="ok">OK, no fix needed</div>';
        }
    }

    echo '<div style="margin-top:24px" class="warn"><strong>Delete this file after running!</strong></div>';

} catch (PDOException $e) {
    echo '<div class="err">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</body></html>';
