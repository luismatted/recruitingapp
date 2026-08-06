<?php
require_once 'config.php';
require_once 'verify.php';

verifyAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// Hostinger WAF blocks PUT/DELETE with a body — allow POST override
if ($method === 'POST' && isset($_GET['_method'])) {
    $override = strtoupper($_GET['_method']);
    if (in_array($override, ['PUT', 'DELETE'])) {
        $method = $override;
    }
}

if ($method === 'GET') {
    $candidates = $db->query("
        SELECT id, name, email, linkedin, location, current_job_title, skills, seniority,
               short_description, best_match_job_id, best_match_score, match_results,
               script_answers, source, status, created_at
        FROM talent_pool ORDER BY created_at DESC
    ")->fetchAll();

    $jds = $db->query("
        SELECT id, company_name, contact_name, email, title, summary, category, location,
               salary, employment_type, skills, best_match_candidate_id, best_match_score,
               match_results, script_answers, source, status, created_at
        FROM landing_jds ORDER BY created_at DESC
    ")->fetchAll();

    echo json_encode(['candidates' => $candidates, 'jds' => $jds]);

} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? '';
    $type = ($data['type'] ?? '') === 'jd' ? 'landing_jds' : 'talent_pool';
    $status = $data['status'] ?? '';

    if (!in_array($status, ['new', 'contacted', 'converted', 'archived'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        exit;
    }

    $stmt = $db->prepare("UPDATE {$type} SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['success' => true]);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? '';
    $type = ($data['type'] ?? '') === 'jd' ? 'landing_jds' : 'talent_pool';

    $stmt = $db->prepare("DELETE FROM {$type} WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
