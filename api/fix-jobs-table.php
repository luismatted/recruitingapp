<?php
/**
 * Check/fix jobs table structure
 */
require_once 'config.php';

try {
    $db = getDB();
    $cols = $db->query("SHOW COLUMNS FROM jobs")->fetchAll(PDO::FETCH_ASSOC);
    echo "JOBS TABLE COLUMNS:\n";
    foreach ($cols as $c) {
        echo "  " . $c['Field'] . " | " . $c['Type'] . ($c['Null'] === 'NO' ? ' | NOT NULL' : '') . ($c['Default'] ? ' | DEFAULT ' . $c['Default'] : '') . "\n";
    }
    echo "\n" . count($cols) . " columns total\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
