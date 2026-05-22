<?php

declare(strict_types=1);

use App\Models\StripeKey;

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();

$input = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        StripeKey::create($input);
        log_activity('Stripe key created', 'stripe');
        json_response(['message' => 'Stripe key created']);
    }
    if ($method === 'PUT') {
        StripeKey::update((int) ($input['id'] ?? 0), $input);
        log_activity('Stripe key updated', 'stripe');
        json_response(['message' => 'Stripe key updated']);
    }
    if ($method === 'DELETE') {
        StripeKey::delete((int) ($input['id'] ?? 0));
        log_activity('Stripe key deleted', 'stripe');
        json_response(['message' => 'Stripe key deleted']);
    }
} catch (Throwable $exception) {
    json_response(['error' => 'Unable to save Stripe key'], 422);
}

json_response(['error' => 'Method not allowed'], 405);
