<?php
/**
 * Quick auth test - upload and visit to check auth works
 */
require_once 'config.php';
header('Content-Type: text/plain');
echo "Auth Test\n=========\n\n";

// Test 1: Check stored hash
echo "1. Stored hash: " . getStoredHash() . "\n";
echo "   Expected:     1396232173\n\n";

// Test 2: Verify correct hash
echo "2. Testing correct hash (POST):\n";
try {
    $db = getDB();
    $stmt = $db->query("SELECT value FROM settings WHERE `key`='pw_hash' LIMIT 1");
    $row = $stmt->fetch();
    echo "   Database value: " . ($row ? $row['value'] : 'NOT FOUND') . "\n";
} catch (Exception $e) {
    echo "   DB Error: " . $e->getMessage() . "\n";
}

// Test 3: Hash check
echo "\n3. JS hash for 'talent2025' should be: 1396232173\n";
echo "   Direct check: " . (getStoredHash() === '1396232173' ? 'MATCHES' : 'MISMATCH') . "\n";

echo "\nDelete this file after testing.\n";
