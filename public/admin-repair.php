<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$repairToken = 'storehub-repair-2026-change-after-use';
$providedToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if (!hash_equals($repairToken, $providedToken)) {
    http_response_code(404);
    exit('Not found');
}

$message = null;
$error = null;
$email = 'ameerhamzadeveloper@gmail.com';
$password = 'admin123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? $email, FILTER_VALIDATE_EMAIL) ?: $email;
    $password = (string) ($_POST['password'] ?? $password);

    try {
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

        $message = 'Admin login was reset successfully.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$users = [];
try {
    $users = db()->query('SELECT id, name, email, role, LEFT(password_hash, 7) hash_prefix, CHAR_LENGTH(password_hash) hash_length FROM users ORDER BY id ASC LIMIT 10')->fetchAll();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Hub Admin Repair</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-panel animate-rise" style="width:min(760px,100%)">
        <div class="brand-mark">SH</div>
        <h1>Admin Repair</h1>
        <p>Use this once to reset the live admin login, then delete this file from the server.</p>
        <?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="stack">
            <input type="hidden" name="token" value="<?= e($repairToken) ?>">
            <label>Email<input name="email" type="email" value="<?= e($email) ?>" required></label>
            <label>New password<input name="password" value="<?= e($password) ?>" required></label>
            <button class="btn primary" type="submit">Reset admin login</button>
        </form>

        <h2>Live database check</h2>
        <p>Database: <?= e(app_config('database')['name']) ?></p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Hash prefix</th><th>Hash length</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e((string) $user['id']) ?></td>
                            <td><?= e($user['name']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><?= e($user['role']) ?></td>
                            <td><?= e($user['hash_prefix']) ?></td>
                            <td><?= e((string) $user['hash_length']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p><a class="btn ghost" href="login.php">Go to login</a></p>
    </main>
</body>
</html>
