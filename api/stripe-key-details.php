<?php

declare(strict_types=1);

use App\Models\StripeKey;

require __DIR__ . '/_bootstrap.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    json_response(['error' => 'Invalid key id'], 422);
}

$details = StripeKey::details($id);
if (!$details) {
    json_response(['error' => 'Stripe key not found'], 404);
}

json_response($details);
