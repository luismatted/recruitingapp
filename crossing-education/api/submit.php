<?php
/**
 * Crossing Education - Public CV Submission
 * Creates a candidate record from the website submit form
 * 
 * POST {name, email, role, location, cv} → Creates candidate
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

try {
    $db = getDB();
    $d = getJsonBody();
    
    // Validate
    $name = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');
    
    if (!$name || !$email) {
        jsonError('Name and email are required', 400);
    }
    
    // Build CV text with email header
    $cvText = "Email: $email\n\n" . ($d['cv'] ?? '');
    
    $stmt = $db->prepare("INSERT INTO `candidates` (`name`, `current_role`, `location`, `cv_text`, `stage`, `status`, `parsed_data`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $name,
        trim($d['role'] ?? ''),
        trim($d['location'] ?? ''),
        $cvText,
        'SUBMITTED',
        'active',
        '{}'
    ]);
    
    $id = (int)$db->lastInsertId();
    jsonOk(['success' => true, 'id' => $id, 'message' => 'Application received. We will review your profile and be in touch soon.']);
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
