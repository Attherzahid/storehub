<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../public/bootstrap.php';

$email = $argv[1] ?? 'ameerhamzadeveloper@gmail.com';
$password = $argv[2] ?? 'admin123';

echo "Checking Store Hub login\n";
echo "Database: " . app_config('database')['name'] . "\n";
echo "Email: {$email}\n";

$stmt = db()->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "Result: user not found\n";
    echo "Existing users:\n";
    foreach (db()->query('SELECT id, email, role FROM users ORDER BY id ASC LIMIT 10') as $row) {
        echo "- #{$row['id']} {$row['email']} ({$row['role']})\n";
    }
    exit(1);
}

$hash = (string) $user['password_hash'];
echo "User id: {$user['id']}\n";
echo "Role: {$user['role']}\n";
echo "Hash length: " . strlen($hash) . "\n";
echo "Hash prefix: " . substr($hash, 0, 7) . "\n";
echo "Password verifies: " . (password_verify($password, $hash) ? 'yes' : 'no') . "\n";

if (!password_verify($password, $hash)) {
    echo "Tip: run php scripts/reset-admin.php {$email} {$password}\n";
    exit(1);
}

echo "Result: login credentials are valid\n";
