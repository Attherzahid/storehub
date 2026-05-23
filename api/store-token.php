<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();

$input = request_input();
$storeId = (int) ($input['store_id'] ?? 0);

if ($storeId < 1) {
    json_response(['error' => 'Invalid store id'], 422);
}

$storeStmt = db()->prepare('SELECT id, name FROM stores WHERE id = ? LIMIT 1');
$storeStmt->execute([$storeId]);
$store = $storeStmt->fetch();

if (!$store) {
    json_response(['error' => 'Store not found'], 404);
}

$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);

$stmt = db()->prepare('INSERT INTO store_connections (store_id, token_hash, status, created_at) VALUES (?, ?, "active", NOW())
    ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), status = "active"');
$stmt->execute([$storeId, $hash]);

log_activity('API token generated for ' . $store['name'], 'store');

json_response([
    'message' => 'Store API token generated. Copy it now; it will not be shown again.',
    'token' => $token,
    'store' => $store['name'],
]);
