<?php
/**
 * Crossing Education - Jobs API (Simplified)
 * Core: title + description. AI evaluates from raw text.
 */
require_once 'config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    if ($method === 'GET') {
        $rows = $db->query("SELECT * FROM jobs ORDER BY display_order ASC, created_at DESC")->fetchAll();
        jsonOk(['jobs' => normalizeRows($rows)]);
    }

    if ($method === 'POST') {
        $d = getJsonBody();
        if (empty($d['title'])) jsonError('Title required', 400);

        $skills = isset($d['skills']) ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) : '[]';
        $parsed = isset($d['parsedData']) ? (is_array($d['parsedData']) ? json_encode($d['parsedData']) : $d['parsedData']) : '{}';
        $maxOrder = $db->query("SELECT MAX(display_order) FROM jobs")->fetchColumn() ?: 0;
        $displayOrder = (int)$maxOrder + 1;

        $stmt = $db->prepare("INSERT INTO jobs (`title`,`company`,`location`,`salary`,`description`,`requirements`,`skills`,`status`,`display_order`,`parsed_data`) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($d['title']), trim($d['company'] ?? ''), trim($d['location'] ?? ''),
            trim($d['salary'] ?? ''), $d['description'] ?? '', $d['requirements'] ?? '',
            $skills, $d['status'] ?? 'active', $displayOrder, $parsed
        ]);
        jsonOk(['success' => true, 'id' => (int)$db->lastInsertId(), 'displayOrder' => $displayOrder]);
    }

    if ($method === 'PUT') {
        $d = getJsonBody();
        if (empty($d['id'])) jsonError('ID required', 400);

        $skills = isset($d['skills']) ? (is_array($d['skills']) ? json_encode($d['skills']) : $d['skills']) : '[]';
        $parsed = isset($d['parsedData']) ? (is_array($d['parsedData']) ? json_encode($d['parsedData']) : $d['parsedData']) : '{}';

        $stmt = $db->prepare("UPDATE jobs SET `title`=?,`company`=?,`location`=?,`salary`=?,`description`=?,`requirements`=?,`skills`=?,`status`=?,`parsed_data`=? WHERE `id`=?");
        $stmt->execute([
            trim($d['title'] ?? ''), trim($d['company'] ?? ''), trim($d['location'] ?? ''),
            trim($d['salary'] ?? ''), $d['description'] ?? '', $d['requirements'] ?? '',
            $skills, $d['status'] ?? 'active', $parsed, (int)$d['id']
        ]);
        jsonOk(['success' => true]);
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        if (!$id) jsonError('ID required', 400);
        $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([(int)$id]);
        jsonOk(['success' => true]);
    }

    jsonError('Method not allowed', 405);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
