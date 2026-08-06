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

// Simple hardcoded hash — change this password!
$correctHash = '$2b$10$PUvrJ1jODVncG/oRpsNTIO3nuDHNc/XXnz8DQFOzvZQjVUJ55UuCG'; // password = "antahr"

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
