<?php

declare(strict_types=1);

use App\Models\StripeKey;

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();
throttle_api('stripe-payout-sync:' . ($_SESSION['user_id'] ?? 'guest'));

$input = request_input();
$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    json_response(['error' => 'Invalid Stripe key.'], 422);
}

try {
    $result = StripeKey::refreshPayout($id);
    log_activity('Stripe payout status refreshed', 'stripe');
    json_response($result);
} catch (Throwable $exception) {
    json_response(['error' => $exception->getMessage()], 422);
}
