<?php
require_once 'config.php';
require_once 'verify.php';

$adminEmail = verifyAdmin();

$jobId = $_GET['job_id'] ?? '';

if (empty($jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID is required']);
    exit;
}

$stmt = $db->prepare("SELECT title, created_by FROM jobs WHERE id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job not found']);
    exit;
}

if ($job['created_by'] && $job['created_by'] !== $adminEmail) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM candidates WHERE job_id = ? ORDER BY match_score DESC");
$stmt->execute([$jobId]);
$candidates = $stmt->fetchAll();

$filename = preg_replace('/[^a-z0-9]/i', '_', $job['title']) . '_candidates.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Candidates name', 'Associated job', 'Skills', 'Current job title', 'Short description', 'CV', 'LinkedIn URL', 'Gender', 'Age range', 'Region', 'Nationality', 'Seniority']);

foreach ($candidates as $c) {
    fputcsv($output, [
        $c['name'],
        $job['title'],
        $c['skills'] ?? '',
        $c['current_job_title'] ?? '',
        $c['short_description'] ?? '',
        $c['cv'] ?? '',
        $c['linkedin'] ?? '',
        $c['gender'] ?? '',
        $c['age_range'] ?? '',
        $c['region'] ?? '',
        $c['nationality'] ?? '',
        $c['seniority'] ?? ''
    ]);
}

fclose($output);
exit;
?>