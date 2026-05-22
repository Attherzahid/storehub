<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Store Hub',
        'url' => getenv('APP_URL') ?: 'https://storehub.orpixia.com',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Karachi',
        'env' => getenv('APP_ENV') ?: 'production',
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'orpiemma_storehub',
        'user' => getenv('DB_USER') ?: 'orpiemma_main',
        'pass' => getenv('DB_PASS') ?: 'CHANGE_ME_IN_LIVE_CONFIG',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'encryption_key' => getenv('APP_KEY') ?: 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
        'session_name' => 'store_hub_session',
        'api_rate_limit' => 120,
    ],
    'stripe' => [
        'api_version' => '2024-06-20',
    ],
];
