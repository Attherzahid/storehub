<?php

declare(strict_types=1);

use App\Models\Dashboard;

require __DIR__ . '/_bootstrap.php';
require_auth();
throttle_api('analytics:' . ($_SESSION['user_id'] ?? 'guest'));

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$storeId = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int) $_GET['store_id'] : null;

if ($from || $to || $storeId) {
    $fromDate = date_create((string) ($from ?: '-24 hours'));
    $toDate = date_create((string) ($to ?: 'now'));
    if (!$fromDate || !$toDate) {
        json_response(['error' => 'Invalid date range'], 422);
    }

    json_response(Dashboard::analyticsReport(
        $storeId,
        $fromDate->format('Y-m-d H:i:s'),
        $toDate->format('Y-m-d H:i:s')
    ));
}

json_response([
    'metrics' => Dashboard::metrics(),
    'charts' => Dashboard::charts(),
    'transactions' => Dashboard::recentTransactions(5),
]);
