<?php
require_once 'config-public.php';

checkRateLimit($db, 'public-chat');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$leadId = preg_replace('/[^a-f0-9]/', '', $data['lead_id'] ?? '');
$scriptType = ($data['script_type'] ?? '') === 'company' ? 'company' : 'candidate';
$step = intval($data['step'] ?? -1);
$answer = trim($data['answer'] ?? '');

if (empty($leadId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lead reference']);
    exit;
}

/* ---------------- scripted funnels (Luis's advice, cleaned) ---------------- */
$SCRIPTS = [
    'candidate' => [
        [
            'q' => "While your profile stays in our screening cycle, let's make sure opportunities can actually find you. First: <strong>do you have a LinkedIn account?</strong>",
            'advice' => "Then that's your first step. LinkedIn is one of the most popular recruitment platforms in the world — the level of seriousness and the detailed information about companies, jobs and candidates is truly remarkable, and so far no other platform matches it. One note: in some markets, like China, LinkedIn isn't the strongest channel for local jobs — but in every country and every market, it remains the best place to find international opportunities.",
            'ack' => "Good — you're on the map."
        ],
        [
            'q' => "<strong>Do you have a professional profile photo, and a banner image related to your industry?</strong>",
            'advice' => "That's worth fixing this week. Your profile photo and banner are the first things recruiters see. Skip the holiday pictures and the generic coffee-cup banner — go to Canva, and with just a few minutes of work you can create a banner that actually speaks about your profession, your company, or your own business.",
            'ack' => "Strong start — first impressions matter."
        ],
        [
            'q' => "<strong>Do you have more than 100 connections?</strong>",
            'advice' => "Here's why that number matters: a LinkedIn profile with fewer than 100 connections looks highly suspicious to recruiters — in fact, a serious recruiter won't spend an InMail or a connection request on a profile below that threshold. Start sending connection requests to people you want to follow, colleagues, and professionals in your field of interest.",
            'ack' => "A solid network multiplies your visibility in recruiter searches."
        ],
        [
            'q' => "<strong>Is your About section written around the keywords of your target role?</strong>",
            'advice' => "Think of your About section as your own website — and LinkedIn is the search engine. You want the right SEO. Keywords are everything: think carefully about what you do and choose your words wisely. Recruiters search things like \"financial analysis\", \"retail industry\", \"sales\" — if those three keywords are in your About section, recruiters will find you. Avoid generic descriptions; describe what you actually do.",
            'ack' => "Excellent — that's exactly what most candidates neglect."
        ]
    ],
    'company' => [
        [
            'q' => "While we keep screening candidates for your role, let's check your reach. First: <strong>are your recruiters active on all major platforms — LinkedIn, Liepin, BOSS Zhipin?</strong>",
            'advice' => "That's a coverage gap — most specialized talent is passive and fragmented across platforms, and single-platform posting misses them entirely.",
            'ack' => "Good coverage — that's rarer than you'd think."
        ],
        [
            'q' => "<strong>Do your recruiters manage more than one language?</strong>",
            'advice' => "Recruiting in more than one language can boost your rate of suitable candidates dramatically. Think about it: people scroll LinkedIn on their phones, and the chances they stop for a job posting in their own language are far higher. The same applies to interviews — a candidate shows their true potential in their native tongue. That said, English remains the foundation for international communication, so at minimum, make sure that's covered.",
            'ack' => "That's a real advantage for international roles."
        ],
        [
            'q' => "<strong>Do you have access to premium tools like LinkedIn Recruiter?</strong>",
            'advice' => "Paid VIP subscriptions can make a real difference. LinkedIn Recruiter, for example, is one of the best tools available — though expensive. If the resources aren't there, there are alternatives: our own subscription, for instance, gives you access to LinkedIn Recruiter through us.",
            'ack' => "Then you know how much targeting they unlock."
        ],
        [
            'q' => "Last one: <strong>do you have an active talent pool — or are you losing candidates across databases and endless Excel lists?</strong>",
            'advice' => "You're not alone — but it doesn't have to be that way. Cloud platforms, AI and dashboards can change that completely. Contact us and we'll help you set it up.",
            'ack' => "Impressive — a live talent pool is a serious competitive edge."
        ]
    ]
];

$PITCH = [
    'candidate' => "That's the foundation — and it's where the free guidance ends. There's more that decides who gets chosen between matches: headline keywords, the Featured section, recommendations, open-to-work signals…\n\nThat's exactly what we do in our <strong>one-on-one sessions</strong>: we take your LinkedIn presence and CV to a professional level, tailored to your industry. Your chances of getting noticed improve dramatically.\n\nWrite to us at <strong>contact@werecruit4you.pro</strong> — mention \"profile session\" and we'll take it from there.",
    'company' => "Here's the summary: your role is registered and stays in our screening cycle — but <strong>reaching the right candidates is a channel problem</strong>, and there's more to it: audience targeting, multilingual outreach, direct sourcing…\n\nThat's what we do: we <strong>promote your position directly to the right candidates</strong>, on the right platforms, in the right language.\n\nWrite to us at <strong>contact@werecruit4you.pro</strong> — mention \"job promotion\" and we'll take it from there."
];

$steps = $SCRIPTS[$scriptType];
$table = ($scriptType === 'company') ? 'landing_jds' : 'talent_pool';

// verify lead exists
$stmt = $db->prepare("SELECT id, script_answers FROM {$table} WHERE id = ?");
$stmt->execute([$leadId]);
$lead = $stmt->fetch();
if (!$lead) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found. Please upload your document again.']);
    exit;
}

$response = ['success' => true];

/* ---------------- process the answer to the previous question ---------------- */
if ($step >= 0 && $answer !== '') {
    $answeredStep = $steps[$step] ?? null;

    // interpret the answer with AI (sentiment + short note)
    $interp = callAI($apiKey,
        "Question asked: \"" . strip_tags($answeredStep['q'] ?? '') . "\"\nUser answer: \"{$answer}\"\n\nReturn JSON: {\"sentiment\":\"positive\"|\"negative\"|\"neutral\", \"note\":\"very short note max 10 words\"}",
        'You interpret short user answers in a recruitment chat. Positive means the user has/does the thing asked. Negative means they do not. Neutral if unclear.'
    );
    $sentiment = $interp['sentiment'] ?? 'neutral';
    $note = $interp['note'] ?? '';

    // store the answer
    $answers = json_decode($lead['script_answers'] ?? '[]', true) ?: [];
    $answers[] = ['step' => $step, 'answer' => mb_substr($answer, 0, 500), 'sentiment' => $sentiment];
    $stmt = $db->prepare("UPDATE {$table} SET script_answers = ? WHERE id = ?");
    $stmt->execute([json_encode($answers), $leadId]);

    // free advice only when the answer reveals a gap
    if ($sentiment === 'negative' && $answeredStep) {
        $response['advice'] = $answeredStep['advice'];
    } elseif ($sentiment === 'positive' && $answeredStep) {
        $response['ack'] = $answeredStep['ack'];
    }
}

/* ---------------- next question or pitch ---------------- */
$nextStep = $step + 1;
if ($nextStep < count($steps)) {
    $response['question'] = $steps[$nextStep]['q'];
    $response['step'] = $nextStep;
    $response['done'] = false;
} else {
    $response['done'] = true;
    $response['pitch'] = $PITCH[$scriptType];
}

echo json_encode($response);
?>
