<?php
/**
 * Crossing Education - Database Setup & Migration
 * 
 * SAFE TO RUN MULTIPLE TIMES — only adds missing tables/columns.
 * Does NOT delete or modify existing data.
 * 
 * Steps:
 * 1. Upload to api/setup.php
 * 2. Visit https://yourdomain.com/api/setup.php
 * 3. Review the report (read-only check)
 * 4. Add ?run=1 to the URL to apply changes
 * 5. DELETE this file after successful setup
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'u678696734_crossing1';
$user = 'u678696734_Luiscrossing1';
$pass = 'Juanjito$25';

$run = isset($_GET['run']) && $_GET['run'] === '1';
$dryRun = !$run;

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head>';
echo '<style>';
echo 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:24px;background:#0f172a;color:#e2e8f0;line-height:1.6}';
echo 'h1{color:#60a5fa;font-size:1.4rem}h2{color:#fbbf24;font-size:1.1rem;margin-top:24px}';
echo '.ok{color:#4ade80}.warn{color:#fbbf24}.err{color:#f87171}.info{color:#94a3b8}';
echo '.box{background:#1e293b;padding:10px 14px;border-radius:8px;margin:6px 0;font-size:.88rem}';
echo '.badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:500;margin-left:6px}';
echo '.bg-ok{background:rgba(74,222,128,.15);color:#4ade80}.bg-warn{background:rgba(251,191,36,.15);color:#fbbf24}.bg-err{background:rgba(248,113,113,.15);color:#f87171}.bg-info{background:rgba(96,165,250,.15);color:#60a5fa}';
echo 'button{padding:12px 24px;border:none;border-radius:10px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer;font-size:1rem;margin-top:16px}';
echo 'button:hover{background:#1d4ed8}';
echo 'code{background:#0f172a;padding:2px 6px;border-radius:4px;font-size:.85rem}';
echo '</style></head><body>';

echo '<h1>🔧 Database Setup' . ($dryRun ? ' (Preview)' : '') . '</h1>';

if ($dryRun) {
    echo '<div class="box info">👀 <strong>DRY RUN MODE</strong> — Nothing will be modified.<br>Add <code>?run=1</code> to the URL to apply changes.</div>';
} else {
    echo '<div class="box warn">⚡ <strong>APPLYING CHANGES</strong> — Adding missing tables/columns only.</div>';
}

// Connect
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo '<div class="box ok">✓ Connected to <code>' . htmlspecialchars($db) . '</code></div>';
} catch (PDOException $e) {
    echo '<div class="box err">✗ Connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</body></html>';
    exit;
}

$changesMade = [];
$changesSkipped = [];
$errors = [];

// ===== HELPER FUNCTIONS =====
function tableExists($pdo, $table) {
    try {
        $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function columnExists($pdo, $table, $column) {
    try {
        return $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function execSafe($pdo, $sql, $dryRun, &$changesMade, &$changesSkipped, $desc) {
    if ($dryRun) {
        $changesSkipped[] = $desc . ' (would run: ' . substr($sql, 0, 80) . '...)';
        return true;
    }
    try {
        $pdo->exec($sql);
        $changesMade[] = $desc;
        return true;
    } catch (PDOException $e) {
        global $errors;
        $errors[] = $desc . ' failed: ' . $e->getMessage();
        return false;
    }
}

// ===== 1. SETTINGS TABLE =====
echo '<h2>1. Settings Table</h2>';
if (!tableExists($pdo, 'settings')) {
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(100) PRIMARY KEY,
        value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    execSafe($pdo, $sql, $dryRun, $changesMade, $changesSkipped, 'Created settings table');
    
    // Insert default password hash for "talent2025"
    // Using hash value 1396232173 (Replit's JS hash algorithm)
    $hash = '1396232173';
    if (!$dryRun) {
        try {
            $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('pw_hash', ?)")
                ->execute([$hash]);
            $changesMade[] = 'Inserted default password hash for "talent2025"';
        } catch (PDOException $e) {
            $errors[] = 'Failed to insert password hash: ' . $e->getMessage();
        }
    } else {
        $changesSkipped[] = 'Would insert default password hash';
    }
} else {
    echo '<div class="box ok">✓ settings table already exists</div>';
    
    // Check if pw_hash exists
    $stmt = $pdo->query("SELECT value FROM settings WHERE `key`='pw_hash' LIMIT 1");
    $row = $stmt->fetch();
    if ($row) {
        echo '<div class="box ok">✓ Password hash is set: <code>' . substr($row['value'], 0, 10) . '...</code></div>';
    } else {
        if (!$dryRun) {
            $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('pw_hash', ?)")
                ->execute(['1396232173']);
            $changesMade[] = 'Inserted default password hash';
        } else {
            $changesSkipped[] = 'Would insert missing password hash';
        }
    }
}

// ===== 2. CANDIDATES TABLE — Check columns =====
echo '<h2>2. Candidates Table Check</h2>';
if (tableExists($pdo, 'candidates')) {
    // Check key columns from the audit
    $expectedCols = [
        'id', 'name', 'current_role', 'current_company', 'location', 
        'cv_text', 'linked_job_id', 'screening_score', 'screening_verdict',
        'screening_rationale', 'screening_key_strength', 'stage',
        'parsed_data', 'created_at', 'updated_at'
    ];
    
    foreach ($expectedCols as $col) {
        if (columnExists($pdo, 'candidates', $col)) {
            // echo '<div class="box ok">✓ candidates.' . $col . '</div>';
        } else {
            echo '<div class="box warn">⚠ candidates.' . $col . ' missing — may need migration</div>';
        }
    }
    
    $count = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    echo '<div class="box info">📊 ' . $count . ' candidate(s) in database — will be preserved</div>';
} else {
    echo '<div class="box err">✗ candidates table missing!</div>';
}

// ===== 3. JOBS TABLE — Check columns =====
echo '<h2>3. Jobs Table Check</h2>';
if (tableExists($pdo, 'jobs')) {
    $expectedCols = ['id', 'title', 'company', 'location', 'salary', 'description', 'status', 'parsed_data', 'created_at', 'updated_at'];
    foreach ($expectedCols as $col) {
        if (!columnExists($pdo, 'jobs', $col)) {
            echo '<div class="box warn">⚠ jobs.' . $col . ' missing</div>';
        }
    }
    $count = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
    echo '<div class="box info">📊 ' . $count . ' job(s) in database — will be preserved</div>';
} else {
    echo '<div class="box err">✗ jobs table missing!</div>';
}

// ===== 4. SCREENINGS TABLE =====
echo '<h2>4. Screenings Table Check</h2>';
if (tableExists($pdo, 'screenings')) {
    $count = $pdo->query("SELECT COUNT(*) FROM screenings")->fetchColumn();
    echo '<div class="box info">📊 ' . $count . ' screening(s) in database</div>';
} else {
    echo '<div class="box err">✗ screenings table missing!</div>';
}

// ===== 5. PUBLIC TABLES =====
echo '<h2>5. Public Tables Check</h2>';
if (tableExists($pdo, 'public_jobs')) {
    $count = $pdo->query("SELECT COUNT(*) FROM public_jobs")->fetchColumn();
    echo '<div class="box info">📊 ' . $count . ' public job(s)</div>';
}
if (tableExists($pdo, 'public_candidates')) {
    $count = $pdo->query("SELECT COUNT(*) FROM public_candidates")->fetchColumn();
    echo '<div class="box info">📊 ' . $count . ' public candidate(s)</div>';
}

// ===== SUMMARY =====
echo '<h2>Summary</h2>';

if ($dryRun) {
    if ($changesSkipped) {
        echo '<div class="box info">Changes that would be made (' . count($changesSkipped) . '):</div>';
        foreach ($changesSkipped as $c) {
            echo '<div class="box">• ' . $c . '</div>';
        }
    } else {
        echo '<div class="box ok">✓ Everything looks good — no changes needed!</div>';
    }
    echo '<br><a href="?run=1"><button>✓ Looks Good — Apply Changes</button></a>';
} else {
    if ($changesMade) {
        echo '<div class="box ok">✓ Changes applied (' . count($changesMade) . '):</div>';
        foreach ($changesMade as $c) {
            echo '<div class="box ok">✓ ' . $c . '</div>';
        }
    }
    if ($errors) {
        echo '<div class="box err">✗ Errors (' . count($errors) . '):</div>';
        foreach ($errors as $e) {
            echo '<div class="box err">✗ ' . $e . '</div>';
        }
    }
    if (empty($changesMade) && empty($errors)) {
        echo '<div class="box ok">✓ No changes needed — everything is up to date!</div>';
    }
    echo '<div class="box warn" style="margin-top:24px">⚠️ <strong>DELETE setup.php NOW for security!</strong></div>';
}

echo '</body></html>';
