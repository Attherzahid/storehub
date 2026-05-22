<?php

declare(strict_types=1);

use App\Models\Dashboard;

require __DIR__ . '/_bootstrap.php';
require_auth();
throttle_api('analytics:' . ($_SESSION['user_id'] ?? 'guest'));

json_response([
    'metrics' => Dashboard::metrics(),
    'charts' => Dashboard::charts(),
    'transactions' => Dashboard::recentTransactions(5),
]);
