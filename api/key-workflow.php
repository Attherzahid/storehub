<?php

declare(strict_types=1);

use App\Models\StripeKey;

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();

$input = request_input();
$id = (int) ($input['key_id'] ?? 0);
$action = (string) ($input['action'] ?? '');

if ($id < 1) {
    json_response(['error' => 'Invalid Stripe key.'], 422);
}

try {
    if ($action === 'wait') {
        $dueDate = trim((string) ($input['payout_due_date'] ?? ''));
        $replacementId = !empty($input['replacement_key_id']) ? (int) $input['replacement_key_id'] : null;
        StripeKey::moveToPayoutWaiting($id, $dueDate, $replacementId);
        log_activity('Stripe key moved to payout waiting', 'stripe');
        json_response(['message' => 'Key is now waiting for payout.']);
    }

    if ($action === 'payout') {
        $amount = (float) ($input['amount'] ?? 0);
        $currency = trim((string) ($input['currency'] ?? 'USD'));
        $payoutDate = trim((string) ($input['payout_date'] ?? ''));
        if ($amount < 0 || $payoutDate === '') {
            json_response(['error' => 'Enter a valid payout amount and date.'], 422);
        }
        StripeKey::recordPayout($id, $amount, $currency, $payoutDate);
        log_activity('Stripe payout confirmed; key returned to ready list', 'stripe');
        json_response(['message' => 'Payout confirmed. Key is ready with its next target.']);
    }
} catch (Throwable $exception) {
    json_response(['error' => $exception->getMessage()], 422);
}

json_response(['error' => 'Invalid workflow action.'], 422);
