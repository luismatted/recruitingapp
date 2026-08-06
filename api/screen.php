<?php
require_once 'config.php';
require_once 'verify.php';

$adminEmail = verifyAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['candidate_text']) || empty($data['job_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Candidate text and job ID are required']);
    exit;
}

// Verify job ownership
$stmt = $db->prepare("SELECT created_by FROM jobs WHERE id = ?");
$stmt->execute([$data['job_id']]);
$jobOwner = $stmt->fetchColumn();

if ($jobOwner && $jobOwner !== $adminEmail && $adminEmail !== 'public@werecruit4you.pro') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$data['job_id']]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job not found']);
    exit;
}

$conversationHistory = $data['conversation_history'] ?? [];
$isFollowUp = !empty($conversationHistory);

$prompt = "You are an expert recruitment AI screening candidates for this job:\n\n";
$prompt .= "JOB TITLE: {$job['title']}\n";
$prompt .= "JOB DESCRIPTION:\n{$job['jd']}\n\n";
$prompt .= "REQUIRED SKILLS: {$job['skills']}\n\n";

if ($isFollowUp) {
    $prompt .= "This is a FOLLOW-UP conversation. The user is asking a question about the previous screening.\n";
    $prompt .= "Previous candidate profile was:\n{$data['candidate_text']}\n\n";
    $prompt .= "Previous analysis was:\n" . ($data['previous_analysis'] ?? 'No previous analysis') . "\n\n";
    $prompt .= "USER'S QUESTION: {$data['user_question']}\n\n";
    $prompt .= "Answer their question directly and helpfully. If they provide new information about the candidate, update your assessment. Be conversational but professional.\n";
} else {
    $prompt .= "CANDIDATE PROFILE:\n{$data['candidate_text']}\n\n";
    $prompt .= "INSTRUCTIONS:\n";
    $prompt .= "1. First, identify the candidate's most recent and most senior roles. Weight these heavily.\n";
    $prompt .= "2. If the candidate mentions company names, consider what those companies likely do based on their role and location.\n";
    $prompt .= "3. Consider geographic and linguistic context. A Chinese candidate in Beijing with fintech experience likely serves Chinese merchants expanding globally.\n";
    $prompt .= "4. Look for transferable skills even if exact keywords don't match.\n";
    $prompt .= "5. Extract the candidate's full name from the profile if present.\n";
    $prompt .= "6. Be generous with scores for senior candidates who show clear progression in relevant fields.\n\n";
    $prompt .= "Return JSON with these exact fields:\n";
    $prompt .= "- match_score: number 0-100\n";
    $prompt .= "- candidate_name: string (extract the full name from the profile, or empty if not found)\n";
    $prompt .= "- analysis: string with detailed assessment\n";
    $prompt .= "- key_skills_found: array of strings\n";
    $prompt .= "- gaps: array of strings\n";
    $prompt .= "- recommendation: string (Strong Match / Good Match / Moderate Fit / Weak Match)\n";
    $prompt .= "- needs_more_info: boolean\n";
    $prompt .= "- questions_to_ask: array of strings (2-3 specific questions directed to the RECRUITER, not the candidate, if needs_more_info is true)\n";
}

$systemPrompt = "You are a senior recruiter with 15 years experience in fintech and cross-border payments. You understand that LinkedIn profiles are often incomplete, so you read between the lines. You know that Pagsmile, Thunes, dLocal, etc. are LATAM/cross-border payment companies. You know that a Chinese person in Beijing doing 'Account Manager' at a fintech likely handles international merchant acquisition. You are smart, contextual, and generous with senior candidates. When asking questions, ask the RECRUITER (the person using this tool), not the candidate directly.";

$messages = [
    ['role' => 'system', 'content' => $systemPrompt]
];

foreach ($conversationHistory as $msg) {
    $messages[] = $msg;
}

$messages[] = ['role' => 'user', 'content' => $prompt];

$apiData = [
    'model' => 'gpt-4o-mini',
    'messages' => $messages,
    'temperature' => 0.3
];

if (!$isFollowUp) {
    $apiData['response_format'] = ['type' => 'json_object'];
}

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI API error: HTTP ' . $httpCode]);
    exit;
}

$result = json_decode($response, true);
if (!isset($result['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid OpenAI response']);
    exit;
}

$content = $result['choices'][0]['message']['content'];

if ($isFollowUp) {
    echo json_encode([
        'success' => true,
        'is_follow_up' => true,
        'response' => $content,
        'job_id' => $data['job_id']
    ]);
    exit;
}

$parsed = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse JSON response']);
    exit;
}

echo json_encode([
    'success' => true,
    'match_score' => $parsed['match_score'] ?? 0,
    'candidate_name' => $parsed['candidate_name'] ?? '',
    'analysis' => $parsed['analysis'] ?? '',
    'key_skills_found' => $parsed['key_skills_found'] ?? [],
    'gaps' => $parsed['gaps'] ?? [],
    'recommendation' => $parsed['recommendation'] ?? '',
    'needs_more_info' => $parsed['needs_more_info'] ?? false,
    'questions_to_ask' => $parsed['questions_to_ask'] ?? [],
    'job_id' => $data['job_id']
]);
?>