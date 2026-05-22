<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();

$input = request_input();
$keyId = (int) ($input['id'] ?? 0);
$stmt = db()->prepare('SELECT secret_key_encrypted FROM stripe_keys WHERE id=?');
$stmt->execute([$keyId]);
$encrypted = $stmt->fetchColumn();

if (!$encrypted) {
    json_response(['error' => 'Stripe key not found'], 404);
}

$secret = decrypt_secret((string) $encrypted);
if (!str_starts_with($secret, 'sk_')) {
    json_response(['error' => 'Stored Stripe secret is invalid'], 422);
}

json_response([
    'message' => 'Stripe key is formatted correctly. Install stripe/stripe-php and call the Account API here in production.',
    'secret_exposed' => false,
]);
