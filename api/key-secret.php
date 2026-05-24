<?php

declare(strict_types=1);

use App\Models\StripeKey;

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();
throttle_api('stripe-secret-reveal:' . ($_SESSION['user_id'] ?? 'guest'));

$input = request_input();
$id = (int) ($input['id'] ?? 0);
$password = (string) ($input['password'] ?? '');

if ($id < 1 || $password === '') {
    json_response(['error' => 'Key and admin password are required.'], 422);
}

try {
    $secret = StripeKey::revealSecret($id, $password);
    log_activity('Stripe secret key revealed for an admin request', 'security');
    json_response(['secret_key' => $secret]);
} catch (Throwable $exception) {
    json_response(['error' => $exception->getMessage()], 403);
}
