<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Store Hub',
        'url' => getenv('APP_URL') ?: 'http://localhost/store-hub/public',
        'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
        'env' => getenv('APP_ENV') ?: 'production',
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'store_hub',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'encryption_key' => getenv('APP_KEY') ?: 'change-this-32-byte-secret-key!!',
        'session_name' => 'store_hub_session',
        'api_rate_limit' => 120,
    ],
    'stripe' => [
        'api_version' => '2024-06-20',
    ],
];
