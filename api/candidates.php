<?php
require_once 'config.php';
require_once 'verify.php';

$adminEmail = verifyAdmin();

function verifyJobOwnership($db, $jobId, $adminEmail) {
    if ($adminEmail === 'public@werecruit4you.pro') {
        return false;
    }
    $stmt = $db->prepare("SELECT created_by FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $owner = $stmt->fetchColumn();
    if ($owner && $owner !== $adminEmail) {
        return false;
    }
    return true;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $jobId = $_GET['job_id'] ?? '';
    
    if (empty($jobId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Job ID is required']);
        exit;
    }
    
    if (!verifyJobOwnership($db, $jobId, $adminEmail)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT * FROM candidates WHERE job_id = ? ORDER BY match_score DESC");
    $stmt->execute([$jobId]);
    $candidates = $stmt->fetchAll();
    
    echo json_encode(['candidates' => $candidates]);
    
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['job_id']) || empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Job ID and name are required']);
        exit;
    }
    
    if (!verifyJobOwnership($db, $data['job_id'], $adminEmail)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $id = 'cand-' . time() . '-' . rand(1000, 9999);
    
    // Build dynamic INSERT — only columns that are provided
    $fields = ['id', 'job_id', 'name'];
    $values = [$id, $data['job_id'], $data['name']];
    
    $optionalColumns = [
        'email', 'linkedin', 'nationality', 'location', 'match_score',
        'full_text', 'ai_notes', 'admin_notes', 'status',
        'skills', 'current_job_title', 'short_description', 'cv',
        'gender', 'age_range', 'region', 'seniority'
    ];
    
    foreach ($optionalColumns as $col) {
        if (isset($data[$col])) {
            $fields[] = $col;
            $values[] = $data[$col];
        }
    }
    
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO candidates (" . implode(', ', $fields) . ") VALUES (" . $placeholders . ")";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        echo json_encode(['success' => true, 'candidate_id' => $id]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Candidate ID is required']);
        exit;
    }
    
    $checkStmt = $db->prepare("SELECT job_id FROM candidates WHERE id = ?");
    $checkStmt->execute([$data['id']]);
    $jobId = $checkStmt->fetchColumn();
    
    if (!$jobId || !verifyJobOwnership($db, $jobId, $adminEmail)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $fields = [];
    $values = [];
    
    $allowed = ['name','email','linkedin','nationality','location','match_score','full_text','ai_notes','admin_notes','status','skills','current_job_title','short_description','cv','gender','age_range','region','seniority'];
    
    foreach ($allowed as $col) {
        if (isset($data[$col])) {
            $fields[] = $col . ' = ?';
            $values[] = $data[$col];
        }
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $values[] = $data['id'];
    $sql = "UPDATE candidates SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Candidate ID is required']);
        exit;
    }
    
    $checkStmt = $db->prepare("SELECT job_id FROM candidates WHERE id = ?");
    $checkStmt->execute([$data['id']]);
    $jobId = $checkStmt->fetchColumn();
    
    if (!$jobId || !verifyJobOwnership($db, $jobId, $adminEmail)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>