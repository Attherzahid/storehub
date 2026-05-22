<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../public/bootstrap.php';

$email = $argv[1] ?? 'ameerhamzadeveloper@gmail.com';
$password = $argv[2] ?? 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = db()->prepare('SELECT id FROM users ORDER BY id ASC LIMIT 1');
$stmt->execute();
$userId = $stmt->fetchColumn();

if ($userId) {
    $update = db()->prepare('UPDATE users SET email = ?, password_hash = ? WHERE id = ?');
    $update->execute([$email, $hash, $userId]);
} else {
    $insert = db()->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())');
    $insert->execute(['Admin', $email, $hash, 'admin']);
}

echo "Admin login reset.\n";
echo "Email: {$email}\n";
echo "Password: {$password}\n";
