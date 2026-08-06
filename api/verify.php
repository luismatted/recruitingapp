<?php
require_once 'config.php';

function getAdminEmail() {
    $email = '';
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $email = $headers['X-Admin-Email'] ?? '';
    }
    if (empty($email)) {
        $email = $_SERVER['HTTP_X_ADMIN_EMAIL'] ?? '';
    }
    return strtolower(trim($email));
}

function verifyAdmin($email = '') {
    $approvedAdmins = [
        'mattediazluis@anta.com',
        'yuxiaoyan@anta.com',
        'kewanzhi@anta.com'
    ];
    
    if (empty($email)) {
        $email = getAdminEmail();
    }
    
    // Allow public access for landing page job listings
    if ($email === 'public@werecruit4you.pro') {
        return $email;
    }
    
    if (empty($email) || !in_array($email, $approvedAdmins)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    return $email;
}
?>
