<?php
/**
 * Crossing Education - Database Audit
 * Run this ONCE to see what's in your database before making changes.
 * Upload to Hostinger api/ folder, visit: yourdomain.com/api/audit.php
 * Read-only. Does NOT modify anything.
 */

// Hide errors from visitors — we handle them ourselves
ini_set('display_errors', '0');
error_reporting(0);

// Database credentials (same as both versions)
$host = 'localhost';
$db   = 'u678696734_crossing1';
$user = 'u678696734_Luiscrossing1';
$pass = 'Juanjito$25';

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head>';
echo '<style>';
echo 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:24px;background:#0f172a;color:#e2e8f0;line-height:1.6}';
echo 'h1{color:#60a5fa;font-size:1.5rem;margin-bottom:4px}h2{color:#fbbf24;font-size:1.1rem;margin:24px 0 8px}';
echo '.ok{color:#4ade80}.warn{color:#fbbf24}.err{color:#f87171}.info{color:#94a3b8}';
echo '.box{background:#1e293b;padding:10px 14px;border-radius:8px;margin:6px 0;font-size:.88rem}';
echo 'table{width:100%;border-collapse:collapse;font-size:.82rem;margin:8px 0}';
echo 'th,td{padding:6px 10px;text-align:left;border-bottom:1px solid #334155}';
echo 'th{color:#60a5fa;font-weight:600}';
echo 'code{background:#0f172a;padding:2px 6px;border-radius:4px;font-size:.8rem}';
echo '.badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:500;margin-left:6px}';
echo '.bg-ok{background:rgba(74,222,128,.15);color:#4ade80}.bg-warn{background:rgba(251,191,36,.15);color:#fbbf24}.bg-err{background:rgba(248,113,113,.15);color:#f87171}';
echo '</style></head><body>';

echo '<h1>🔍 Database Audit</h1>';
echo '<p class="info">Read-only check. Nothing was modified.</p>';
echo '<p class="info">Generated: ' . date('Y-m-d H:i:s') . '</p>';

// ===== CONNECT =====
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo '<div class="box ok">✓ Connected to database: <code>' . htmlspecialchars($db) . '</code></div>';
} catch (PDOException $e) {
    echo '<div class="box err">✗ Connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</body></html>';
    exit;
}

// ===== LIST TABLES =====
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<h2>📋 Tables Found (' . count($tables) . ')</h2>';
    if (empty($tables)) {
        echo '<div class="box warn">No tables found. Database is empty — run setup.php first.</div>';
    } else {
        echo '<div class="box">' . implode(', ', array_map(function($t){return '<code>' . $t . '</code>';}, $tables)) . '</div>';
    }
} catch (PDOException $e) {
    echo '<div class="box err">Could not list tables: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Expected tables from both versions
$expectedTables = ['settings', 'jobs', 'candidates', 'screenings', 'public_jobs', 'public_candidates'];
$missing = array_diff($expectedTables, $tables);
$extra = array_diff($tables, $expectedTables);

if ($missing) {
    echo '<div class="box warn">⚠ Missing expected tables: ' . implode(', ', array_map(function($t){return '<code>' . $t . '</code>';}, $missing)) . '</div>';
}
if ($extra) {
    echo '<div class="box info">ℹ Extra tables (not from our app): ' . implode(', ', array_map(function($t){return '<code>' . $t . '</code>';}, $extra)) . '</div>';
}

// ===== DETAILED TABLE INFO =====
foreach ($tables as $tableName) {
    echo '<h2>📦 Table: <code>' . $tableName . '</code></h2>';
    
    // Columns
    try {
        $cols = $pdo->query("DESCRIBE `$tableName`")->fetchAll();
        echo '<table><thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>';
        foreach ($cols as $col) {
            echo '<tr>';
            echo '<td><code>' . $col['Field'] . '</code></td>';
            echo '<td>' . $col['Type'] . '</td>';
            echo '<td>' . $col['Null'] . '</td>';
            echo '<td>' . ($col['Key'] ? $col['Key'] : '—') . '</td>';
            echo '<td>' . ($col['Default'] !== null ? '<code>' . $col['Default'] . '</code>' : 'NULL') . '</td>';
            echo '<td>' . ($col['Extra'] ? $col['Extra'] : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } catch (PDOException $e) {
        echo '<div class="box err">Could not describe: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    // Row count
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$tableName`")->fetchColumn();
        echo '<div class="box">Rows: <strong>' . $count . '</strong>';
        if ($count > 0) {
            echo ' <span class="badge bg-ok">Has data</span>';
        } else {
            echo ' <span class="badge bg-warn">Empty</span>';
        }
        echo '</div>';
        
        // Sample data (first 3 rows)
        if ($count > 0) {
            $sample = $pdo->query("SELECT * FROM `$tableName` LIMIT 3")->fetchAll();
            echo '<details><summary style="cursor:pointer;color:#60a5fa;font-size:.85rem;margin:8px 0">Show sample data (up to 3 rows)</summary>';
            echo '<pre style="background:#0f172a;padding:12px;border-radius:8px;overflow-x:auto;font-size:.78rem">';
            foreach ($sample as $i => $row) {
                echo '<strong>Row ' . ($i + 1) . ':</strong><br>';
                foreach ($row as $k => $v) {
                    $val = $v === null ? 'NULL' : htmlspecialchars(substr((string)$v, 0, 200));
                    echo '  <code>' . $k . '</code>: ' . $val . '<br>';
                }
                echo '<br>';
            }
            echo '</pre></details>';
        }
    } catch (PDOException $e) {
        echo '<div class="box err">Could not count rows: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ===== SETTINGS CHECK =====
if (in_array('settings', $tables)) {
    echo '<h2>⚙️ Settings</h2>';
    try {
        $settings = $pdo->query("SELECT * FROM settings")->fetchAll();
        if ($settings) {
            foreach ($settings as $s) {
                $val = $s['key'] === 'pw_hash' ? substr($s['value'], 0, 10) . '...' : htmlspecialchars($s['value']);
                echo '<div class="box"><code>' . $s['key'] . '</code>: ' . $val . '</div>';
            }
            // Check if hash matches expected
            $hashRow = $pdo->query("SELECT value FROM settings WHERE `key`='pw_hash' LIMIT 1")->fetch();
            if ($hashRow) {
                $expected = '1396232173'; // Replit version
                $actual = (string)$hashRow['value'];
                if ($actual === $expected) {
                    echo '<div class="box ok">✓ Password hash matches expected value for "talent2025"</div>';
                } else {
                    echo '<div class="box warn">⚠ Password hash does NOT match. Expected: <code>' . $expected . '</code>, Got: <code>' . $actual . '</code></div>';
                }
            }
        } else {
            echo '<div class="box warn">settings table exists but is empty</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="box err">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ===== COMPATIBILITY CHECK =====
echo '<h2>🔧 Compatibility Check</h2>';

// Check critical columns that differ between versions
$checks = [
    ['jobs', 'description', 'VARCHAR or TEXT'],
    ['jobs', 'parsedData', 'TEXT or JSON'],
    ['candidates', 'currentRole', 'VARCHAR'],
    ['candidates', 'currentCompany', 'VARCHAR'],
    ['candidates', 'cvText', 'TEXT'],
    ['candidates', 'screeningScore', 'INT'],
    ['candidates', 'screeningVerdict', 'VARCHAR'],
    ['candidates', 'screeningRationale', 'TEXT'],
    ['candidates', 'screeningKeyStrength', 'TEXT'],
    ['candidates', 'linkedJobId', 'INT'],
    ['public_jobs', 'desc', 'TEXT (reserved word!)'],
    ['public_jobs', 'description', 'TEXT (safe name)'],
    ['screenings', 'candidateId', 'INT'],
    ['screenings', 'candidate_id', 'INT'],
];

foreach ($checks as [$table, $col, $expected]) {
    if (!in_array($table, $tables)) continue;
    try {
        $hasCol = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->rowCount() > 0;
        if ($hasCol) {
            echo '<div class="box ok">✓ <code>' . $table . '.' . $col . '</code> exists (' . $expected . ')</div>';
        } else {
            echo '<div class="box warn">⚠ <code>' . $table . '.' . $col . '</code> MISSING — may cause errors</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="box err">✗ Error checking ' . $table . '.' . $col . '</div>';
    }
}

echo '<hr style="border-color:#334155;margin:32px 0">';
echo '<p class="info">End of audit. Screenshot this page or save the HTML. Delete this file after review.</p>';
echo '</body></html>';
