<?php

declare(strict_types=1);

require __DIR__ . '/../public/bootstrap.php';

function require_ajax_admin(): void
{
    require_auth();
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        json_response(['error' => 'Administrator access required'], 403);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf($token)) {
        json_response(['error' => 'Invalid CSRF token'], 419);
    }
}

function throttle_api(string $key): void
{
    $limit = (int) app_config('security')['api_rate_limit'];
    $_SESSION['rate_limits'][$key] ??= ['count' => 0, 'reset' => time() + 60];
    if ($_SESSION['rate_limits'][$key]['reset'] < time()) {
        $_SESSION['rate_limits'][$key] = ['count' => 0, 'reset' => time() + 60];
    }
    $_SESSION['rate_limits'][$key]['count']++;
    if ($_SESSION['rate_limits'][$key]['count'] > $limit) {
        json_response(['error' => 'Rate limit exceeded'], 429);
    }
}
