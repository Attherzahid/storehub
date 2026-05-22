<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$repairToken = 'storehub-login-check-2026-change-after-use';
$providedToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if (!hash_equals($repairToken, $providedToken)) {
    http_response_code(404);
    exit('Not found');
}

$email = (string) ($_POST['email'] ?? 'ameerhamzadeveloper@gmail.com');
$password = (string) ($_POST['password'] ?? '');
$result = null;
$users = [];
$error = null;

try {
    $users = db()->query('SELECT id, email, role, LEFT(password_hash, 7) hash_prefix, CHAR_LENGTH(password_hash) hash_length FROM users ORDER BY id ASC LIMIT 10')->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = db()->prepare('SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $result = 'No user found for this email in the database used by the live app.';
        } elseif (password_verify($password, (string) $user['password_hash'])) {
            $result = 'Password verifies: YES. If login still fails, browser session/cookie or CSRF is the next thing to check.';
        } else {
            $result = 'Password verifies: NO. The password does not match the hash stored for this email.';
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Hub Login Check</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-panel animate-rise" style="width:min(760px,100%)">
        <div class="brand-mark">SH</div>
        <h1>Login Check</h1>
        <p>This checks the exact database and hash used by the live app.</p>
        <?php if ($result): ?><div class="alert"><?= e($result) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="token" value="<?= e($repairToken) ?>">
            <label>Email<input name="email" type="email" value="<?= e($email) ?>" required></label>
            <label>Password to test<input name="password" value="<?= e($password) ?>" required></label>
            <button class="btn primary" type="submit">Check password</button>
        </form>
        <h2>Live app database</h2>
        <p><?= e(app_config('database')['name']) ?> @ <?= e(app_config('database')['host']) ?></p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Email</th><th>Role</th><th>Hash prefix</th><th>Hash length</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e((string) $user['id']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><?= e($user['role']) ?></td>
                            <td><?= e($user['hash_prefix']) ?></td>
                            <td><?= e((string) $user['hash_length']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
