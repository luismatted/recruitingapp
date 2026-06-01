<?php
/**
 * Crossing Education - Public API
 * Serves data to the public website
 * 
 * GET    ?type=jobs|candidates → Open, no auth
 * POST   ?type=jobs|candidates → Admin only (create)
 * PUT    ?type=jobs|candidates → Admin only (update)
 * DELETE ?type=jobs|candidates&id=ID → Admin only (delete)
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? '';

try {
    $db = getDB();
    
    // ========== JOBS ==========
    if ($type === 'jobs') {
        
        // LIST (public, no auth)
        if ($method === 'GET') {
            $rows = $db->query("SELECT * FROM public_jobs ORDER BY created_at DESC")->fetchAll();
            $result = [];
            foreach ($rows as $row) {
                $item = normalizeKeys($row);
                // Parse skills JSON
                if (!empty($item['skills']) && is_string($item['skills'])) {
                    $decoded = json_decode($item['skills'], true);
                    $item['skills'] = is_array($decoded) ? $decoded : [];
                } else {
                    $item['skills'] = [];
                }
                // Ensure description field exists (mapping from desc if needed)
                if (empty($item['description']) && !empty($item['desc'])) {
                    $item['description'] = $item['desc'];
                }
                $result[] = $item;
            }
            jsonOk(['jobs' => $result]);
        }
        
        // All write operations require auth
        requireAuth();
        
        // CREATE
        if ($method === 'POST') {
            $d = getJsonBody();
            
            if (empty($d['title'])) {
                jsonError('Title is required', 400);
            }
            
            $skills = isset($d['skills']) 
                ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) 
                : '[]';
            $sourceJobId = isset($d['sourceJobId']) && $d['sourceJobId'] ? (int)$d['sourceJobId'] : null;
            
            $stmt = $db->prepare("INSERT INTO `public_jobs` (`source_job_id`, `title`, `company`, `location`, `salary`, `job_type`, `description`, `skills`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sourceJobId,
                trim($d['title']),
                trim($d['company'] ?? ''),
                trim($d['location'] ?? ''),
                trim($d['salary'] ?? ''),
                trim($d['jobType'] ?? $d['job_type'] ?? ''),
                $d['description'] ?? $d['desc'] ?? '',
                $skills
            ]);
            
            jsonOk(['success' => true, 'id' => (int)$db->lastInsertId()]);
        }
        
        // UPDATE
        if ($method === 'PUT') {
            $d = getJsonBody();
            
            if (empty($d['id'])) {
                jsonError('ID is required', 400);
            }
            
            $skills = isset($d['skills']) 
                ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) 
                : '[]';
            
            $stmt = $db->prepare("UPDATE `public_jobs` SET `title` = ?, `company` = ?, `location` = ?, `salary` = ?, `job_type` = ?, `description` = ?, `skills` = ? WHERE `id` = ?");
            $stmt->execute([
                trim($d['title'] ?? ''),
                trim($d['company'] ?? ''),
                trim($d['location'] ?? ''),
                trim($d['salary'] ?? ''),
                trim($d['jobType'] ?? $d['job_type'] ?? ''),
                $d['description'] ?? $d['desc'] ?? '',
                $skills,
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
            
            $db->prepare("DELETE FROM `public_jobs` WHERE `id` = ?")->execute([(int)$id]);
            jsonOk(['success' => true]);
        }
    }
    
    // ========== CANDIDATES ==========
    if ($type === 'candidates') {
        
        // LIST (public, no auth)
        if ($method === 'GET') {
            $rows = $db->query("SELECT * FROM public_candidates ORDER BY created_at DESC")->fetchAll();
            $result = [];
            foreach ($rows as $row) {
                $item = normalizeKeys($row);
                // Parse skills JSON
                if (!empty($item['skills']) && is_string($item['skills'])) {
                    $decoded = json_decode($item['skills'], true);
                    $item['skills'] = is_array($decoded) ? $decoded : [];
                } else {
                    $item['skills'] = [];
                }
                // Map experience → exp for JS frontend
                if (!empty($item['experience']) && empty($item['exp'])) {
                    $item['exp'] = $item['experience'];
                }
                $result[] = $item;
            }
            jsonOk(['candidates' => $result]);
        }
        
        // All write operations require auth
        requireAuth();
        
        // CREATE
        if ($method === 'POST') {
            $d = getJsonBody();
            
            if (empty($d['name'])) {
                jsonError('Name is required', 400);
            }
            
            $skills = isset($d['skills']) 
                ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) 
                : '[]';
            $sourceCandidateId = isset($d['sourceCandidateId']) && $d['sourceCandidateId'] ? (int)$d['sourceCandidateId'] : null;
            
            $stmt = $db->prepare("INSERT INTO `public_candidates` (`source_candidate_id`, `name`, `role`, `location`, `experience`, `highlight`, `skills`) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sourceCandidateId,
                trim($d['name']),
                trim($d['role'] ?? ''),
                trim($d['location'] ?? ''),
                trim($d['exp'] ?? $d['experience'] ?? ''),
                $d['highlight'] ?? '',
                $skills
            ]);
            
            jsonOk(['success' => true, 'id' => (int)$db->lastInsertId()]);
        }
        
        // UPDATE
        if ($method === 'PUT') {
            $d = getJsonBody();
            
            if (empty($d['id'])) {
                jsonError('ID is required', 400);
            }
            
            $skills = isset($d['skills']) 
                ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) 
                : '[]';
            
            $stmt = $db->prepare("UPDATE `public_candidates` SET `name` = ?, `role` = ?, `location` = ?, `experience` = ?, `highlight` = ?, `skills` = ? WHERE `id` = ?");
            $stmt->execute([
                trim($d['name'] ?? ''),
                trim($d['role'] ?? ''),
                trim($d['location'] ?? ''),
                trim($d['exp'] ?? $d['experience'] ?? ''),
                $d['highlight'] ?? '',
                $skills,
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
            
            $db->prepare("DELETE FROM `public_candidates` WHERE `id` = ?")->execute([(int)$id]);
            jsonOk(['success' => true]);
        }
    }
    
    // Unknown type
    jsonError('Invalid type. Use ?type=jobs or ?type=candidates', 400);
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
