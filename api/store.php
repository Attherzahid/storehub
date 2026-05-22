<?php

declare(strict_types=1);

use App\Models\Store;

require __DIR__ . '/_bootstrap.php';
require_ajax_admin();

$input = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        Store::create($input);
        log_activity('Store created', 'store');
        json_response(['message' => 'Store created']);
    }
    if ($method === 'PUT') {
        Store::update((int) ($input['id'] ?? 0), $input);
        log_activity('Store updated', 'store');
        json_response(['message' => 'Store updated']);
    }
    if ($method === 'DELETE') {
        Store::delete((int) ($input['id'] ?? 0));
        log_activity('Store deleted', 'store');
        json_response(['message' => 'Store deleted']);
    }
} catch (Throwable $exception) {
    json_response(['error' => 'Unable to save store'], 422);
}

json_response(['error' => 'Method not allowed'], 405);
