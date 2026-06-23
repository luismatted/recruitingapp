<?php
/**
 * Crossing Education - Candidates API
 * Internal candidate management (admin only)
 * 
 * GET    → List all candidates
 * POST   → Create candidate
 * PUT    → Update candidate
 * DELETE ?id=ID → Delete candidate
 */
require_once 'config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    // LIST
    if ($method === 'GET') {
        $rows = $db->query("SELECT * FROM candidates ORDER BY created_at DESC")->fetchAll();
        jsonOk(['candidates' => normalizeRows($rows)]);
    }
    
    // CREATE
    if ($method === 'POST') {
        $d = getJsonBody();
        
        if (empty($d['name'])) {
            jsonError('Name is required', 400);
        }
        
        $parsedData = isset($d['parsedData']) && is_array($d['parsedData']) 
            ? json_encode($d['parsedData']) 
            : (isset($d['parsed_data']) ? (is_string($d['parsed_data']) ? $d['parsed_data'] : json_encode($d['parsed_data'])) : '{}');
        
        $screeningScore = isset($d['screeningScore']) ? (int)$d['screeningScore'] : null;
        $linkedJobId = isset($d['linkedJobId']) && $d['linkedJobId'] ? (int)$d['linkedJobId'] : null;
        
        $stmt = $db->prepare("INSERT INTO candidates (`name`, `current_role`, `current_company`, `location`, `cv_text`, `linked_job_id`, `screening_score`, `screening_verdict`, `screening_rationale`, `screening_key_strength`, `stage`, `parsed_data`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($d['name']),
            trim($d['currentRole'] ?? $d['current_role'] ?? ''),
            trim($d['currentCompany'] ?? $d['current_company'] ?? ''),
            trim($d['location'] ?? ''),
            $d['cvText'] ?? $d['cv_text'] ?? '',
            $linkedJobId,
            $screeningScore,
            $d['screeningVerdict'] ?? $d['screening_verdict'] ?? null,
            $d['screeningRationale'] ?? $d['screening_rationale'] ?? null,
            $d['screeningKeyStrength'] ?? $d['screening_key_strength'] ?? null,
            $d['stage'] ?? 'PENDING',
            $parsedData
        ]);
        
        $id = (int)$db->lastInsertId();
        jsonOk(['success' => true, 'id' => $id]);
    }
    
    // UPDATE
    if ($method === 'PUT') {
        $d = getJsonBody();
        
        if (empty($d['id'])) {
            jsonError('ID is required', 400);
        }
        
        $parsedData = isset($d['parsedData']) && is_array($d['parsedData']) 
            ? json_encode($d['parsedData']) 
            : (isset($d['parsed_data']) ? (is_string($d['parsed_data']) ? $d['parsed_data'] : json_encode($d['parsed_data'])) : '{}');
        
        $screeningScore = isset($d['screeningScore']) ? (int)$d['screeningScore'] : null;
        $linkedJobId = isset($d['linkedJobId']) && $d['linkedJobId'] ? (int)$d['linkedJobId'] : null;
        
        $stmt = $db->prepare("UPDATE candidates SET `name` = ?, `current_role` = ?, `current_company` = ?, `location` = ?, `cv_text` = ?, `linked_job_id` = ?, `screening_score` = ?, `screening_verdict` = ?, `screening_rationale` = ?, `screening_key_strength` = ?, `stage` = ?, `parsed_data` = ? WHERE `id` = ?");
        $stmt->execute([
            trim($d['name'] ?? ''),
            trim($d['currentRole'] ?? $d['current_role'] ?? ''),
            trim($d['currentCompany'] ?? $d['current_company'] ?? ''),
            trim($d['location'] ?? ''),
            $d['cvText'] ?? $d['cv_text'] ?? '',
            $linkedJobId,
            $screeningScore,
            $d['screeningVerdict'] ?? $d['screening_verdict'] ?? null,
            $d['screeningRationale'] ?? $d['screening_rationale'] ?? null,
            $d['screeningKeyStrength'] ?? $d['screening_key_strength'] ?? null,
            $d['stage'] ?? 'PENDING',
            $parsedData,
            (int)$d['id']
        ]);
        
        jsonOk(['success' => true]);
    }
    
    // DELETE
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            jsonError('ID is required', 400);
        }
        
        $db->prepare("DELETE FROM `candidates` WHERE `id` = ?")->execute([(int)$id]);
        jsonOk(['success' => true]);
    }
    
    jsonError('Method not allowed', 405);
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
