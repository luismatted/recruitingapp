<?php
/**
 * Check/fix candidates table structure
 */
require_once 'config.php';

try {
    $db = getDB();
    $cols = $db->query("SHOW COLUMNS FROM candidates")->fetchAll(PDO::FETCH_ASSOC);
    echo "CANDIDATES TABLE COLUMNS:\n";
    foreach ($cols as $c) {
        echo "  " . $c['Field'] . " | " . $c['Type'] . ($c['Null'] === 'NO' ? ' | NOT NULL' : '') . ($c['Default'] ? ' | DEFAULT ' . $c['Default'] : '') . "\n";
    }
    echo "\n" . count($cols) . " columns total\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
