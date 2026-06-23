<?php
/**
 * Crossing Education - Auth API
 * 
 * GET  ?auth=HASH     → Check if hash is valid
 * POST {hash: HASH}   → Verify hash for login
 * POST {newHash: H}   → Change password (requires valid auth)
 * DELETE              → Clear auth (no-op, client handles logout)
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    // GET — check if provided auth hash is valid
    if ($method === 'GET') {
        $auth = $_GET['auth'] ?? '';
        jsonOk(['authenticated' => ($auth === getStoredHash())]);
    }
    
    // POST — verify hash or change password
    if ($method === 'POST') {
        $d = getJsonBody();
        
        // Verify a hash (login)
        if (isset($d['hash'])) {
            $hash = (string)$d['hash'];
            $ok = ($hash === getStoredHash());
            jsonOk(['authenticated' => $ok, 'hash' => $hash]);
        }
        
        // Change password (requires valid auth param)
        if (isset($d['newHash'])) {
            requireAuth();
            $newHash = (string)$d['newHash'];
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('pw_hash', ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$newHash, $newHash]);
            jsonOk(['success' => true]);
        }
        
        jsonError('Invalid request. Send {hash} to login or {newHash} to change password.', 400);
    }
    
    // DELETE — logout (no-op, auth is client-side)
    if ($method === 'DELETE') {
        jsonOk(['authenticated' => false]);
    }
    
    jsonError('Method not allowed', 405);
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
