<?php
require_once 'config.php';
require_once 'verify.php';

$adminEmail = verifyAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Public access: show active jobs without candidates
    if ($adminEmail === 'public@werecruit4you.pro') {
        $stmt = $db->query("SELECT id, title, summary, category, jd, location, salary, start_date, hiring_manager, department, employment_type, skills, stage, created_at FROM jobs WHERE stage IN ('hiring', 'offer', 'planning') ORDER BY created_at DESC");
        $jobs = $stmt->fetchAll();
        echo json_encode(['jobs' => $jobs]);
        exit;
    }
    
    // Admin access: show their jobs + legacy unassigned jobs, with candidates
    $stmt = $db->prepare("SELECT * FROM jobs WHERE created_by = ? OR created_by IS NULL OR created_by = '' ORDER BY created_at DESC");
    $stmt->execute([$adminEmail]);
    $jobs = $stmt->fetchAll();
    
    foreach ($jobs as &$job) {
        $stmt = $db->prepare("SELECT * FROM candidates WHERE job_id = ?");
        $stmt->execute([$job['id']]);
        $job['candidates'] = $stmt->fetchAll();
    }
    
    echo json_encode(['jobs' => $jobs]);
    
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        exit;
    }
    
    $id = 'job-' . time() . '-' . rand(1000, 9999);
    
    $stmt = $db->prepare("
        INSERT INTO jobs (id, title, summary, category, jd, location, salary, start_date, hiring_manager, department, employment_type, skills, stage, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $id,
        $data['title'] ?? '',
        $data['summary'] ?? '',
        $data['category'] ?? '',
        $data['jd'] ?? '',
        $data['location'] ?? '',
        $data['salary'] ?? '',
        $data['start_date'] ?? '',
        $data['hiring_manager'] ?? '',
        $data['department'] ?? '',
        $data['employment_type'] ?? 'Full-time',
        $data['skills'] ?? '',
        $data['stage'] ?? 'planning',
        $adminEmail
    ]);
    
    echo json_encode(['success' => true, 'job_id' => $id]);
    
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Job ID is required']);
        exit;
    }
    
    // Verify ownership
    $checkStmt = $db->prepare("SELECT created_by FROM jobs WHERE id = ?");
    $checkStmt->execute([$data['id']]);
    $jobOwner = $checkStmt->fetchColumn();
    
    if ($jobOwner && $jobOwner !== $adminEmail) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only edit your own jobs']);
        exit;
    }
    
    if (isset($data['title'])) {
        $stmt = $db->prepare("
            UPDATE jobs SET 
                title = ?, 
                summary = ?, 
                category = ?, 
                jd = ?, 
                location = ?, 
                salary = ?, 
                start_date = ?, 
                hiring_manager = ?, 
                department = ?, 
                employment_type = ?, 
                skills = ?, 
                stage = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['title'] ?? '',
            $data['summary'] ?? '',
            $data['category'] ?? '',
            $data['jd'] ?? '',
            $data['location'] ?? '',
            $data['salary'] ?? '',
            $data['start_date'] ?? '',
            $data['hiring_manager'] ?? '',
            $data['department'] ?? '',
            $data['employment_type'] ?? 'Full-time',
            $data['skills'] ?? '',
            $data['stage'] ?? 'planning',
            $data['id']
        ]);
    } else {
        $stmt = $db->prepare("UPDATE jobs SET stage = ? WHERE id = ?");
        $stmt->execute([$data['stage'], $data['id']]);
    }
    
    echo json_encode(['success' => true]);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Job ID is required']);
        exit;
    }
    
    $checkStmt = $db->prepare("SELECT created_by FROM jobs WHERE id = ?");
    $checkStmt->execute([$data['id']]);
    $jobOwner = $checkStmt->fetchColumn();
    
    if ($jobOwner && $jobOwner !== $adminEmail) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only delete your own jobs']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM candidates WHERE job_id = ?");
    $stmt->execute([$data['id']]);
    
    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>