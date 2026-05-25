<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$connection = require_api_token();
throttle_api('store-key:' . $connection['id']);

$stmt = db()->prepare('
    SELECT k.id, k.company_name, k.public_key, k.secret_key_encrypted
    FROM stores s
    INNER JOIN stripe_keys k ON k.id = s.stripe_key_id
    WHERE s.id = ?
      AND s.status IN ("active", "syncing")
      AND k.status = "active"
      AND k.workflow_status = "ready"
    LIMIT 1
');
$stmt->execute([(int) $connection['store_id']]);
$key = $stmt->fetch();

if (!$key) {
    json_response(['error' => 'No ready Stripe key is assigned to this store.'], 409);
}

$secretKey = decrypt_secret((string) $key['secret_key_encrypted']);
if ($secretKey === '') {
    json_response(['error' => 'The assigned Stripe key cannot be decrypted.'], 422);
}

header('Cache-Control: no-store, private');
json_response([
    'key_id' => (int) $key['id'],
    'company_name' => (string) $key['company_name'],
    'public_key' => (string) $key['public_key'],
    'secret_key' => $secretKey,
]);
