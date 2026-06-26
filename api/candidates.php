<?php
/**
 * Crossing Education - Candidates API (Simplified)
 * Core: name + cvText. AI handles all evaluation from raw text.
 */
require_once 'config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    if ($method === 'GET') {
        $rows = $db->query("SELECT * FROM candidates ORDER BY created_at DESC")->fetchAll();
        jsonOk(['candidates' => normalizeRows($rows)]);
    }

    if ($method === 'POST') {
        $d = getJsonBody();
        if (empty($d['name'])) jsonError('Name is required', 400);

        $skills = isset($d['skills']) ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) : '[]';
        $parsed = isset($d['parsedData']) ? (is_array($d['parsedData']) ? json_encode($d['parsedData']) : $d['parsedData']) : '{}';
        $linkedJobId = !empty($d['linkedJobId']) ? (int)$d['linkedJobId'] : null;

        $stmt = $db->prepare("INSERT INTO candidates (`name`,`current_role`,`current_company`,`location`,`email`,`phone`,`linked_in`,`cv_text`,`seniority`,`skills`,`linked_job_id`,`screening_score`,`screening_verdict`,`screening_rationale`,`screening_key_strength`,`stage`,`parsed_data`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($d['name']),
            trim($d['currentRole'] ?? $d['current_role'] ?? ''),
            trim($d['currentCompany'] ?? $d['current_company'] ?? ''),
            trim($d['location'] ?? ''),
            trim($d['email'] ?? ''),
            trim($d['phone'] ?? ''),
            trim($d['linkedIn'] ?? $d['linked_in'] ?? ''),
            $d['cvText'] ?? $d['cv_text'] ?? '',
            trim($d['seniority'] ?? ''),
            $skills,
            $linkedJobId,
            isset($d['screeningScore']) ? (int)$d['screeningScore'] : null,
            $d['screeningVerdict'] ?? $d['screening_verdict'] ?? null,
            $d['screeningRationale'] ?? $d['screening_rationale'] ?? null,
            $d['screeningKeyStrength'] ?? $d['screening_key_strength'] ?? null,
            $d['stage'] ?? 'PENDING',
            $parsed
        ]);
        jsonOk(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }

    if ($method === 'PUT') {
        $d = getJsonBody();
        if (empty($d['id'])) jsonError('ID required', 400);

        $skills = isset($d['skills']) ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) : '[]';
        $parsed = isset($d['parsedData']) ? (is_array($d['parsedData']) ? json_encode($d['parsedData']) : $d['parsedData']) : '{}';
        $linkedJobId = !empty($d['linkedJobId']) ? (int)$d['linkedJobId'] : null;
        $screeningScore = isset($d['screeningScore']) ? (int)$d['screeningScore'] : null;

        $stmt = $db->prepare("UPDATE candidates SET `name`=?,`current_role`=?,`current_company`=?,`location`=?,`email`=?,`phone`=?,`linked_in`=?,`cv_text`=?,`seniority`=?,`skills`=?,`linked_job_id`=?,`screening_score`=?,`screening_verdict`=?,`screening_rationale`=?,`screening_key_strength`=?,`stage`=?,`parsed_data`=? WHERE `id`=?");
        $stmt->execute([
            trim($d['name'] ?? ''), trim($d['currentRole'] ?? $d['current_role'] ?? ''),
            trim($d['currentCompany'] ?? $d['current_company'] ?? ''), trim($d['location'] ?? ''),
            trim($d['email'] ?? ''), trim($d['phone'] ?? ''), trim($d['linkedIn'] ?? $d['linked_in'] ?? ''),
            $d['cvText'] ?? $d['cv_text'] ?? '', trim($d['seniority'] ?? ''), $skills,
            $linkedJobId, $screeningScore,
            $d['screeningVerdict'] ?? $d['screening_verdict'] ?? null,
            $d['screeningRationale'] ?? $d['screening_rationale'] ?? null,
            $d['screeningKeyStrength'] ?? $d['screening_key_strength'] ?? null,
            $d['stage'] ?? 'PENDING', $parsed, (int)$d['id']
        ]);
        jsonOk(['success' => true]);
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!$id) jsonError('ID required', 400);
        $db->prepare("DELETE FROM candidates WHERE id = ?")->execute([(int)$id]);
        jsonOk(['success' => true]);
    }

    jsonError('Method not allowed', 405);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
