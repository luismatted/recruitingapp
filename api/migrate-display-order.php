<?php
/**
 * Migration: Add display_order column to jobs table
 * Assigns sequential 1,2,3... numbers to existing jobs
 */
require_once 'config.php';

try {
    $db = getDB();
    
    // Check if column exists
    $cols = $db->query("SHOW COLUMNS FROM jobs LIKE 'display_order'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE jobs ADD COLUMN display_order INT UNSIGNED NULL");
        echo "Added display_order column\n";
    } else {
        echo "display_order column already exists\n";
    }
    
    // Assign sequential numbers to existing jobs (ordered by created_at)
    $jobs = $db->query("SELECT id FROM jobs ORDER BY created_at ASC")->fetchAll(PDO::FETCH_COLUMN);
    $order = 1;
    foreach ($jobs as $jobId) {
        $db->prepare("UPDATE jobs SET display_order = ? WHERE id = ?")->execute([$order, $jobId]);
        echo "Job #{$jobId} → display_order {$order}\n";
        $order++;
    }
    
    echo "\nMigration complete. " . count($jobs) . " jobs updated.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
