<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'keys', 'key-details', 'stores', 'analytics', 'settings'];

if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = '404';
}

view($page, [
    'currentPage' => $page,
    'user' => current_user(),
]);
