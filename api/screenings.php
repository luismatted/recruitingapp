<?php
/**
 * Crossing Education - Screenings API
 * AI screening results (admin only)
 * 
 * GET    → List all screenings
 * POST   → Create screening
 * DELETE ?id=ID → Delete screening
 */
require_once 'config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    // LIST
    if ($method === 'GET') {
        $rows = $db->query("SELECT * FROM screenings ORDER BY created_at DESC")->fetchAll();
        jsonOk(['screenings' => normalizeRows($rows)]);
    }
    
    // CREATE
    if ($method === 'POST') {
        $d = getJsonBody();
        
        $candidateId = (int)($d['candidateId'] ?? $d['candidate_id'] ?? 0);
        $jobId = (int)($d['jobId'] ?? $d['job_id'] ?? 0);
        
        if (!$candidateId || !$jobId) {
            jsonError('candidateId and jobId are required', 400);
        }
        
        $stmt = $db->prepare("INSERT INTO `screenings` (`candidate_id`, `job_id`, `verdict`, `score`, `rationale`, `key_strength`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $candidateId,
            $jobId,
            $d['verdict'] ?? 'MAYBE',
            (int)($d['score'] ?? 50),
            $d['rationale'] ?? '',
            $d['keyStrength'] ?? $d['key_strength'] ?? ''
        ]);
        
        $id = (int)$db->lastInsertId();
        jsonOk(['success' => true, 'id' => $id]);
    }
    
    // DELETE
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            jsonError('ID is required', 400);
        }
        
        $db->prepare("DELETE FROM `screenings` WHERE `id` = ?")->execute([(int)$id]);
        jsonOk(['success' => true]);
    }
    
    jsonError('Method not allowed', 405);
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
