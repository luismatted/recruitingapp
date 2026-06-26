<?php
/**
 * Crossing Education - Jobs API
 * Internal job management (admin only)
 * 
 * GET    → List all jobs
 * POST   → Create job
 * PUT    → Update job
 * DELETE ?id=ID → Delete job
 */
require_once 'config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    // LIST
    if ($method === 'GET') {
        $rows = $db->query("SELECT * FROM jobs ORDER BY created_at DESC")->fetchAll();
        jsonOk(['jobs' => normalizeRows($rows)]);
    }
    
    // CREATE
    if ($method === 'POST') {
        $d = getJsonBody();
        
        if (empty($d['title'])) {
            jsonError('Title is required', 400);
        }
        
        $parsedData = isset($d['parsedData']) && is_array($d['parsedData']) 
            ? json_encode($d['parsedData']) 
            : (isset($d['parsed_data']) ? (is_string($d['parsed_data']) ? $d['parsed_data'] : json_encode($d['parsed_data'])) : '{}');
        
        // Auto-assign next display_order
        $maxOrder = $db->query("SELECT MAX(display_order) FROM jobs")->fetchColumn() ?: 0;
        $displayOrder = (int)$maxOrder + 1;
        
        $stmt = $db->prepare("INSERT INTO `jobs` (`title`, `company`, `location`, `salary`, `description`, `status`, `parsed_data`, `display_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($d['title']),
            trim($d['company'] ?? ''),
            trim($d['location'] ?? ''),
            trim($d['salary'] ?? ''),
            $d['description'] ?? '',
            $d['status'] ?? 'active',
            $parsedData,
            $displayOrder
        ]);
        
        $id = (int)$db->lastInsertId();
        jsonOk(['success' => true, 'id' => $id, 'displayOrder' => $displayOrder]);
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
        
        $stmt = $db->prepare("UPDATE `jobs` SET `title` = ?, `company` = ?, `location` = ?, `salary` = ?, `description` = ?, `status` = ?, `parsed_data` = ? WHERE `id` = ?");
        $stmt->execute([
            trim($d['title'] ?? ''),
            trim($d['company'] ?? ''),
            trim($d['location'] ?? ''),
            trim($d['salary'] ?? ''),
            $d['description'] ?? '',
            $d['status'] ?? 'active',
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
        
        $db->prepare("DELETE FROM `jobs` WHERE `id` = ?")->execute([(int)$id]);
        jsonOk(['success' => true]);
    }
    
    jsonError('Method not allowed', 405);
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
