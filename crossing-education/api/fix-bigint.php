<?php
/**
 * Fix: Change int columns to bigint to handle large auto-increment values
 * Upload to api/fix-bigint.php, visit once, then delete
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
echo '<h2>BigInt Migration Fix</h2>';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<div class="ok">Connected</div>';

    // Fix jobs table: change id to bigint unsigned and reset auto-increment
    echo '<h3>Fixing jobs table...</h3>';
    
    // First, change the column type to bigint unsigned
    $pdo->exec("ALTER TABLE jobs MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    echo '<div class="ok">Changed jobs.id to BIGINT UNSIGNED</div>';
    
    // Reset auto-increment to max + 1
    $maxRow = $pdo->query("SELECT MAX(id) as max_id FROM jobs")->fetch(PDO::FETCH_ASSOC);
    $maxId = $maxRow['max_id'] ?? 0;
    $newAI = $maxId ? ($maxId + 1) : 1;
    $pdo->exec("ALTER TABLE jobs AUTO_INCREMENT = $newAI");
    echo '<div class="ok">Set AUTO_INCREMENT to ' . $newAI . '</div>';
    
    // Also fix linked_job_id in candidates table (references jobs.id)
    $pdo->exec("ALTER TABLE candidates MODIFY COLUMN linked_job_id BIGINT UNSIGNED NULL");
    echo '<div class="ok">Changed candidates.linked_job_id to BIGINT UNSIGNED</div>';
    
    // Also fix source_job_id in public_jobs
    $pdo->exec("ALTER TABLE public_jobs MODIFY COLUMN source_job_id BIGINT UNSIGNED NULL");
    echo '<div class="ok">Changed public_jobs.source_job_id to BIGINT UNSIGNED</div>';

    // Fix candidates table id too (prevent future issues)
    $pdo->exec("ALTER TABLE candidates MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    echo '<div class="ok">Changed candidates.id to BIGINT UNSIGNED</div>';
    
    // Fix public_jobs id
    $pdo->exec("ALTER TABLE public_jobs MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    echo '<div class="ok">Changed public_jobs.id to BIGINT UNSIGNED</div>';
    
    // Fix public_candidates id
    $pdo->exec("ALTER TABLE public_candidates MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    echo '<div class="ok">Changed public_candidates.id to BIGINT UNSIGNED</div>';
    
    // Fix screenings id and foreign key references
    $pdo->exec("ALTER TABLE screenings MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    $pdo->exec("ALTER TABLE screenings MODIFY COLUMN candidate_id BIGINT UNSIGNED NOT NULL");
    $pdo->exec("ALTER TABLE screenings MODIFY COLUMN job_id BIGINT UNSIGNED NOT NULL");
    echo '<div class="ok">Changed screenings columns to BIGINT UNSIGNED</div>';
    
    // Fix source_candidate_id
    $pdo->exec("ALTER TABLE public_candidates MODIFY COLUMN source_candidate_id BIGINT UNSIGNED NULL");
    echo '<div class="ok">Changed public_candidates.source_candidate_id to BIGINT UNSIGNED</div>';

    echo '<h3>All fixes applied!</h3>';
    echo '<div class="warn"><strong>Delete this file now!</strong></div>';

} catch (PDOException $e) {
    echo '<div class="err">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</body></html>';
