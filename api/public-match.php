<?php
require_once 'config-public.php';

checkRateLimit($db, 'public-match');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/* ---------------- upload validation ---------------- */
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No document received']);
    exit;
}

$file = $_FILES['document'];
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 10 MB)']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'docx', 'doc', 'txt'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported format. Please upload PDF, Word or TXT.']);
    exit;
}

/* ---------------- text extraction ---------------- */
function pdfUnescape($s) {
    $s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
        return chr(octdec($m[1]));
    }, $s);
    return $s;
}

function extractFromPDF($path) {
    $content = file_get_contents($path);
    $text = '';
    // uncompressed text between parentheses in Tj/TJ operators
    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj/i', $content, $m)) {
        foreach ($m[0] as $match) {
            if (preg_match('/\((.*)\)\s*Tj/is', $match, $inner)) {
                $text .= pdfUnescape($inner[1]) . ' ';
            }
        }
    }
    // compressed streams
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $streams)) {
        foreach ($streams[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) continue;
            if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj/i', $decoded, $m)) {
                foreach ($m[0] as $match) {
                    if (preg_match('/\((.*)\)\s*Tj/is', $match, $inner)) {
                        $text .= pdfUnescape($inner[1]) . ' ';
                    }
                }
            }
            // TJ arrays
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrays)) {
                foreach ($arrays[1] as $arr) {
                    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $arr, $parts)) {
                        foreach ($parts[0] as $p) {
                            $text .= pdfUnescape(substr($p, 1, -1));
                        }
                        $text .= ' ';
                    }
                }
            }
        }
    }
    return $text;
}

function extractFromDOCX($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $xml = $zip->readFromName('word/document.xml');
    $zip->close();
    if ($xml === false) return '';
    $xml = preg_replace('/<w:p[ >]/i', "\n<w:p ", $xml);
    $text = strip_tags($xml);
    return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function extractFromDOC($path) {
    $raw = file_get_contents($path);
    // try UTF-16LE (common in Word binary)
    $utf = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
    $text = preg_replace('/[^\x20-\x7E\n\r\táéíóúÁÉÍÓÚñÑüÜàèìòùÀÈÌÒÙâêîôûÂÊÎÔÛäëïöüÄËÏÖÜçÇ]/u', ' ', $utf);
    if (str_word_count($text) < 20) {
        $text = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $raw);
    }
    return $text;
}

function cleanText($t) {
    $t = preg_replace('/\s+/', ' ', $t);
    return trim($t);
}

switch ($ext) {
    case 'pdf':  $text = extractFromPDF($file['tmp_name']); break;
    case 'docx': $text = extractFromDOCX($file['tmp_name']); break;
    case 'doc':  $text = extractFromDOC($file['tmp_name']); break;
    default:     $text = file_get_contents($file['tmp_name']);
}
$text = cleanText($text);

if (str_word_count($text) < 30) {
    http_response_code(422);
    echo json_encode(['error' => 'Could not extract enough text from the document. Please try a different file.']);
    exit;
}

// cap the text we send to the AI
$aiText = mb_substr($text, 0, 12000);

/* ---------------- step 1: classify ---------------- */
$cls = callAI($apiKey,
    "Classify this document. Is it a candidate CV/resume, or a company job description?\n\nDOCUMENT:\n" . $aiText,
    'You classify documents. Return JSON: {"type":"cv"} or {"type":"job"}. If genuinely ambiguous decide by which it most resembles.'
);
if (isset($cls['error'])) {
    http_response_code(502);
    echo json_encode(['error' => 'AI classification failed. Please try again.']);
    exit;
}
$type = (isset($cls['type']) && $cls['type'] === 'job') ? 'job' : 'cv';

/* ---------------- step 2: parse structured fields ---------------- */
if ($type === 'cv') {
    $parsed = callAI($apiKey,
        "Extract structured data from this CV. Return JSON with keys: name, email, linkedin, location, current_job_title, skills (comma-separated string), seniority (Junior/Mid/Senior/Executive), short_description (one professional sentence, max 25 words, third person).\n\nCV:\n" . $aiText,
        'You parse CVs into structured JSON. Use empty string for missing fields. Never invent data.'
    );
} else {
    $parsed = callAI($apiKey,
        "Extract structured data from this job description. Return JSON with keys: company_name, contact_name, email, title, summary (one sentence, max 25 words), category, location, salary, employment_type, skills (comma-separated string).\n\nJOB DESCRIPTION:\n" . $aiText,
        'You parse job descriptions into structured JSON. Use empty string for missing fields. Never invent data.'
    );
}
if (isset($parsed['error'])) {
    http_response_code(502);
    echo json_encode(['error' => 'AI parsing failed. Please try again.']);
    exit;
}

/* ---------------- step 3: batch matching against real data ---------------- */
$MATCH_THRESHOLD = 75;
$matches = [];
$bestScore = 0;
$bestId = null;
$matchResults = [];

if ($type === 'cv') {
    $jobs = $db->query("SELECT id, title, summary, category, location, employment_type, skills FROM jobs")->fetchAll();
    if (count($jobs) > 0) {
        $jobList = '';
        foreach ($jobs as $j) {
            $jobList .= "ID: {$j['id']} | Title: {$j['title']} | Category: {$j['category']} | Location: {$j['location']} | Type: {$j['employment_type']} | Skills: {$j['skills']} | Summary: {$j['summary']}\n";
        }
        $scores = callAI($apiKey,
            "Score this candidate against EACH job below, 0-100, based on skills fit, seniority, location and overall suitability. Be strict and realistic — most candidates do not fit most jobs.\n\nCANDIDATE:\n{$parsed['current_job_title']} | {$parsed['seniority']} | Skills: {$parsed['skills']} | Location: {$parsed['location']}\nProfile: {$parsed['short_description']}\n\nJOBS:\n{$jobList}\n\nReturn JSON: {\"scores\":[{\"id\":\"job id\",\"score\":85}, ...]} — one entry per job, using the exact IDs given.",
            'You are a strict recruitment matching engine. Return only JSON.'
        );
        if (!isset($scores['error']) && isset($scores['scores']) && is_array($scores['scores'])) {
            foreach ($scores['scores'] as $s) {
                $sid = $s['id'] ?? '';
                $sc = intval($s['score'] ?? 0);
                $matchResults[] = ['id' => $sid, 'score' => $sc];
                if ($sc > $bestScore) { $bestScore = $sc; $bestId = $sid; }
            }
            // build reveal list: top 3 above threshold, title + summary only
            $byId = [];
            foreach ($jobs as $j) $byId[$j['id']] = $j;
            usort($matchResults, fn($a, $b) => $b['score'] - $a['score']);
            foreach (array_slice($matchResults, 0, 3) as $r) {
                if ($r['score'] >= $MATCH_THRESHOLD && isset($byId[$r['id']])) {
                    $j = $byId[$r['id']];
                    $matches[] = [
                        'title' => $j['title'],
                        'location' => $j['location'],
                        'summary' => $j['summary'],
                        'score' => $r['score']
                    ];
                }
            }
        }
    }

    $leadId = bin2hex(random_bytes(8));
    $stmt = $db->prepare("INSERT INTO talent_pool (id, name, email, linkedin, location, current_job_title, skills, seniority, short_description, full_text, best_match_job_id, best_match_score, match_results) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $leadId,
        $parsed['name'] ?? '',
        $parsed['email'] ?? '',
        $parsed['linkedin'] ?? '',
        $parsed['location'] ?? '',
        $parsed['current_job_title'] ?? '',
        $parsed['skills'] ?? '',
        $parsed['seniority'] ?? '',
        $parsed['short_description'] ?? '',
        $text,
        $bestId,
        $bestScore,
        json_encode($matchResults)
    ]);

} else {
    $cands = $db->query("SELECT id, name, current_job_title, skills, seniority, location, short_description FROM candidates")->fetchAll();
    if (count($cands) > 0) {
        $candList = '';
        foreach ($cands as $c) {
            $candList .= "ID: {$c['id']} | Name: {$c['name']} | Title: {$c['current_job_title']} | Seniority: {$c['seniority']} | Skills: {$c['skills']} | Location: {$c['location']} | Profile: {$c['short_description']}\n";
        }
        $scores = callAI($apiKey,
            "Score EACH candidate below against this job, 0-100, based on skills fit, seniority, location and overall suitability. Be strict and realistic.\n\nJOB:\n{$parsed['title']} | {$parsed['category']} | Location: {$parsed['location']} | Skills required: {$parsed['skills']}\nSummary: {$parsed['summary']}\n\nCANDIDATES:\n{$candList}\n\nReturn JSON: {\"scores\":[{\"id\":\"candidate id\",\"score\":85}, ...]} — one entry per candidate, using the exact IDs given.",
            'You are a strict recruitment matching engine. Return only JSON.'
        );
        if (!isset($scores['error']) && isset($scores['scores']) && is_array($scores['scores'])) {
            foreach ($scores['scores'] as $s) {
                $sid = $s['id'] ?? '';
                $sc = intval($s['score'] ?? 0);
                $matchResults[] = ['id' => $sid, 'score' => $sc];
                if ($sc > $bestScore) { $bestScore = $sc; $bestId = $sid; }
            }
            $byId = [];
            foreach ($cands as $c) $byId[$c['id']] = $c;
            usort($matchResults, fn($a, $b) => $b['score'] - $a['score']);
            foreach (array_slice($matchResults, 0, 3) as $r) {
                if ($r['score'] >= $MATCH_THRESHOLD && isset($byId[$r['id']])) {
                    $c = $byId[$r['id']];
                    $matches[] = [
                        'name' => $c['name'],
                        'title' => $c['current_job_title'],
                        'description' => $c['short_description'],
                        'score' => $r['score']
                    ];
                }
            }
        }
    }

    $leadId = bin2hex(random_bytes(8));
    $stmt = $db->prepare("INSERT INTO landing_jds (id, company_name, contact_name, email, title, summary, category, jd, location, salary, employment_type, skills, best_match_candidate_id, best_match_score, match_results) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $leadId,
        $parsed['company_name'] ?? '',
        $parsed['contact_name'] ?? '',
        $parsed['email'] ?? '',
        $parsed['title'] ?? '',
        $parsed['summary'] ?? '',
        $parsed['category'] ?? '',
        $text,
        $parsed['location'] ?? '',
        $parsed['salary'] ?? '',
        $parsed['employment_type'] ?? '',
        $parsed['skills'] ?? '',
        $bestId,
        $bestScore,
        json_encode($matchResults)
    ]);
}

echo json_encode([
    'success' => true,
    'lead_id' => $leadId,
    'type' => $type,
    'parsed' => $parsed,
    'match_found' => count($matches) > 0,
    'matches' => $matches,
    'best_score' => $bestScore
]);
?>
