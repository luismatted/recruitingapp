<?php
require_once 'config.php';
require_once 'verify.php';

$adminEmail = verifyAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ---------------------------------------------------------------------------
// 1. Validate the uploaded file
// ---------------------------------------------------------------------------
if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload failed']);
    exit;
}

$file = $_FILES['document'];

// Hard limit: 10 MB
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 10 MB)']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf', 'docx', 'doc', 'txt'];
if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported file type. Please upload PDF, Word (.doc/.docx) or TXT.']);
    exit;
}

// ---------------------------------------------------------------------------
// 2. Extract plain text from the document
// ---------------------------------------------------------------------------
function cleanText($text) {
    // Normalize unicode & whitespace, strip control characters
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function extractFromPDF($path) {
    $content = file_get_contents($path);
    if ($content === false) return '';

    // Scanned/image-only PDFs contain no text streams at all
    if (strpos($content, '/Font') === false && strpos($content, 'BT') === false) {
        return '';
    }

    $text = '';

    // Grab every content stream (supports FlateDecode-compressed and raw)
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $matches)) {
        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = $stream; // maybe uncompressed
            }
            // Only process streams that look like text-drawing operators
            if (strpos($decoded, 'BT') === false) continue;

            // (string) Tj  and  [(a) 12 (b)] TJ  and  ' / " variants
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*(?:Tj|\'|\")/s', $decoded, $strs)) {
                foreach ($strs[1] as $s) { $text .= pdfUnescape($s); }
            }
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrays)) {
                foreach ($arrays[1] as $arr) {
                    if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/s', $arr, $strs)) {
                        $line = '';
                        foreach ($strs[1] as $s) { $line .= pdfUnescape($s); }
                        $text .= $line;
                    }
                    $text .= "\n";
                }
            }
            // Line breaks at text-positioning operators
            $text = preg_replace('/(T[dD\*]|ET)\s*/', "\n", $text);
        }
    }

    return $text;
}

function pdfUnescape($s) {
    // Decode PDF string escapes and common octal sequences
    $s = str_replace(['\\(', '\\)', '\\\\'], ["\x01", "\x02", "\x03"], $s);
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
        return chr(octdec($m[1]));
    }, $s);
    $s = str_replace(["\x01", "\x02", "\x03"], ['(', ')', '\\'], $s);
    $s = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $s);
    return $s;
}

function extractFromDOCX($path) {
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';

    $text = '';
    // Main document + headers/footers
    $parts = ['word/document.xml'];
    for ($i = 1; $i <= 3; $i++) {
        $parts[] = "word/header{$i}.xml";
        $parts[] = "word/footer{$i}.xml";
    }
    foreach ($parts as $part) {
        $xml = $zip->getFromName($part);
        if ($xml === false) continue;
        // Paragraph & break boundaries become newlines/spaces before stripping tags
        $xml = preg_replace('/<w:br\s*\/?>/', "\n", $xml);
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $xml = preg_replace('/<\/w:tr>/', "\n", $xml);
        $xml = preg_replace('/<w:tab\s*\/?>/', ' ', $xml);
        $text .= strip_tags($xml) . "\n";
    }
    $zip->close();
    return $text;
}

function extractFromDOC($path) {
    // Legacy binary .doc — best-effort extraction of readable runs
    $content = file_get_contents($path);
    if ($content === false) return '';
    // Try UTF-16LE first (Word stores text as UTF-16LE internally)
    $utf16 = @mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
    $source = ($utf16 !== false) ? $utf16 : $content;
    // Keep runs of printable characters (4+ chars)
    if (preg_match_all('/[\x20-\x7E\xC0-\xFF\n\r\t]{4,}/u', $source, $m)) {
        return implode("\n", $m[0]);
    }
    return '';
}

switch ($ext) {
    case 'pdf':  $rawText = extractFromPDF($file['tmp_name']);  break;
    case 'docx': $rawText = extractFromDOCX($file['tmp_name']); break;
    case 'doc':  $rawText = extractFromDOC($file['tmp_name']);  break;
    case 'txt':  $rawText = file_get_contents($file['tmp_name']); break;
    default:     $rawText = '';
}

$text = cleanText((string)$rawText);

if (mb_strlen($text) < 40) {
    http_response_code(422);
    echo json_encode(['error' => 'Could not extract readable text from this document. It may be a scanned image PDF — try a text-based PDF or paste the content instead.']);
    exit;
}

// Cap the text sent to the AI (roughly 8k words is plenty for a CV or JD)
$maxChars = 30000;
if (mb_strlen($text) > $maxChars) {
    $text = mb_substr($text, 0, $maxChars);
}

$mode = ($_POST['mode'] ?? 'candidate') === 'job' ? 'job' : 'candidate';

// ---------------------------------------------------------------------------
// 3. Ask the AI to structure the extracted text
// ---------------------------------------------------------------------------
if ($mode === 'job') {
    $prompt = "Extract the job posting information from the document text below and return JSON with these exact fields:\n";
    $prompt .= "- title: job title (string)\n";
    $prompt .= "- summary: one or two sentences summarizing the role for a dashboard card (string)\n";
    $prompt .= "- category: best match from [Finance, Engineering, Marketing, Operations, Sales, HR, Product, Customer Success] (string)\n";
    $prompt .= "- jd: the full job description, cleaned and well formatted with line breaks (string)\n";
    $prompt .= "- location: work location (string, empty if not stated)\n";
    $prompt .= "- salary: salary range (string, empty if not stated)\n";
    $prompt .= "- start_date: start date or availability (string, empty if not stated)\n";
    $prompt .= "- hiring_manager: hiring manager name if stated (string, empty if not stated)\n";
    $prompt .= "- department: department or team (string, empty if not stated)\n";
    $prompt .= "- employment_type: best match from [Full-time, Part-time, Contract, Freelance, Internship] (string)\n";
    $prompt .= "- skills: comma-separated list of the key required skills (string)\n\n";
    $prompt .= "DOCUMENT TEXT:\n" . $text;
    $systemPrompt = 'You are an expert recruitment assistant. You extract structured information from job description documents. Always return valid JSON with exactly the requested fields. Never invent information that is not in the document, except for summary and category which you infer.';
} else {
    $prompt = "Extract the candidate information from the CV/resume text below and return JSON with these exact fields:\n";
    $prompt .= "- name: full name (string)\n";
    $prompt .= "- email: email address (string, empty if not found)\n";
    $prompt .= "- linkedin: LinkedIn URL (string, empty if not found)\n";
    $prompt .= "- nationality: nationality if stated or clearly inferable (string, empty otherwise)\n";
    $prompt .= "- location: current city/country (string, empty if not found)\n";
    $prompt .= "- current_job_title: most recent job title (string)\n";
    $prompt .= "- skills: comma-separated list of key skills (string)\n";
    $prompt .= "- seniority: best match from [Junior, Mid-senior, Senior] based on years of experience and role level (string)\n";
    $prompt .= "- short_description: 2-3 sentence professional summary of the candidate (string)\n";
    $prompt .= "- full_text: the complete CV content, cleaned and well formatted with line breaks (string)\n\n";
    $prompt .= "DOCUMENT TEXT:\n" . $text;
    $systemPrompt = 'You are an expert recruitment assistant. You extract structured information from CVs and resumes. Always return valid JSON with exactly the requested fields. Never invent contact details that are not in the document; leave them empty instead.';
}

$data = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ],
    'response_format' => ['type' => 'json_object'],
    'temperature' => 0.2
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'AI service error: HTTP ' . $httpCode]);
    exit;
}

$result = json_decode($response, true);
if (!isset($result['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid AI response']);
    exit;
}

$parsed = json_decode($result['choices'][0]['message']['content'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse AI response']);
    exit;
}

// Note: the uploaded file is never stored — only the extracted text is used.
echo json_encode([
    'success' => true,
    'mode' => $mode,
    'filename' => $file['name'],
    'extracted_text' => $text,
    'parsed' => $parsed
]);
?>
