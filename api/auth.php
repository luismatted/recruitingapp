<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

$approvedAdmins = [
    'mattediazluis@anta.com',
    'yuxiaoyan@anta.com',
    'kewanzhi@anta.com'
];

// Password: .env ADMIN_PASSWORD_HASH takes priority; fallback built-in hash
$correctHash = $envVars['ADMIN_PASSWORD_HASH'] ?? '$2b$10$Bn9XbSw5q3futiCW/tG6HOk0w92QRx3pI27DiGOmlDN8SezSQXisS'; // antahr

if (!in_array($email, $approvedAdmins)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!password_verify($password, $correctHash)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid password']);
    exit;
}

echo json_encode(['success' => true, 'email' => $email]);
?>
