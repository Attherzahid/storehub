<?php

declare(strict_types=1);

use App\Core\Database;

require __DIR__ . '/../app/Core/Support.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

date_default_timezone_set(app_config('app')['timezone']);
session_name(app_config('security')['session_name']);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

try {
    Database::connection();
} catch (Throwable $exception) {
    if ((app_config('app')['env'] ?? 'production') !== 'production') {
        throw $exception;
    }
}
