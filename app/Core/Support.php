<?php

declare(strict_types=1);

use App\Core\Database;

function app_config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/config.php';
    }

    return $key ? ($config[$key] ?? null) : $config;
}

function db(): PDO
{
    return Database::connection();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function url(string $path = ''): string
{
    $base = rtrim(app_config('app')['url'], '/');
    return $base . '/' . ltrim($path, '/');
}

function view(string $page, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/../Views/pages/' . $page . '.php';
    require __DIR__ . '/../Views/layouts/app.php';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function request_input(): array
{
    $json = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}

function encrypt_secret(string $plain): string
{
    $key = hash('sha256', app_config('security')['encryption_key'], true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decrypt_secret(string $encrypted): string
{
    $raw = base64_decode($encrypted, true);
    if (!$raw || strlen($raw) < 17) {
        return '';
    }

    $key = hash('sha256', app_config('security')['encryption_key'], true);
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv) ?: '';
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_auth(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function log_activity(string $message, string $type = 'info', ?int $userId = null): void
{
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, type, message, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$userId ?? ($_SESSION['user_id'] ?? null), $type, $message, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return $_SERVER['HTTP_X_API_TOKEN'] ?? null;
}

function require_api_token(): array
{
    $token = bearer_token();
    if (!$token) {
        json_response(['error' => 'Missing API token'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT * FROM store_connections WHERE token_hash = ? AND status = "active" LIMIT 1');
    $stmt->execute([$hash]);
    $connection = $stmt->fetch();
    if (!$connection) {
        json_response(['error' => 'Invalid API token'], 403);
    }

    return $connection;
}
