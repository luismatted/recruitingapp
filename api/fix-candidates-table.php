<?php
/**
 * Fix candidates table - add missing email, phone, linked_in columns
 */
require_once 'config.php';

try {
    $db = getDB();
    
    // Check which columns exist
    $cols = $db->query("SHOW COLUMNS FROM candidates")->fetchAll(PDO::FETCH_COLUMN);
    $has = array_flip($cols);
    
    $added = 0;
    
    if (!isset($has['email'])) {
        $db->exec("ALTER TABLE candidates ADD COLUMN email VARCHAR(255) NULL AFTER location");
        echo "Added: email VARCHAR(255)\n";
        $added++;
    }
    if (!isset($has['phone'])) {
        $db->exec("ALTER TABLE candidates ADD COLUMN phone VARCHAR(100) NULL AFTER email");
        echo "Added: phone VARCHAR(100)\n";
        $added++;
    }
    if (!isset($has['linked_in'])) {
        $db->exec("ALTER TABLE candidates ADD COLUMN linked_in VARCHAR(500) NULL AFTER phone");
        echo "Added: linked_in VARCHAR(500)\n";
        $added++;
    }
    if (!isset($has['skills'])) {
        $db->exec("ALTER TABLE candidates ADD COLUMN skills LONGTEXT NULL AFTER linked_in");
        echo "Added: skills LONGTEXT (JSON array)\n";
        $added++;
    }
    if (!isset($has['seniority'])) {
        $db->exec("ALTER TABLE candidates ADD COLUMN seniority VARCHAR(50) NULL AFTER skills");
        echo "Added: seniority VARCHAR(50)\n";
        $added++;
    }
    
    // Also fix jobs table if needed
    $jcols = $db->query("SHOW COLUMNS FROM jobs")->fetchAll(PDO::FETCH_COLUMN);
    $jhas = array_flip($jcols);
    
    if (!isset($jhas['skills'])) {
        $db->exec("ALTER TABLE jobs ADD COLUMN skills LONGTEXT NULL AFTER description");
        echo "Added to jobs: skills LONGTEXT\n";
        $added++;
    }
    if (!isset($jhas['requirements'])) {
        $db->exec("ALTER TABLE jobs ADD COLUMN requirements TEXT NULL AFTER skills");
        echo "Added to jobs: requirements TEXT\n";
        $added++;
    }
    
    echo "\nDone. {$added} column(s) added.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
