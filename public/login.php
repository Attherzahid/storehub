<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Try again.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            log_activity('Admin signed in', 'auth', (int) $user['id']);
            redirect('index.php');
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Store Hub</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
    <main class="auth-panel animate-rise">
        <div class="brand-mark">SH</div>
        <h1>Welcome back</h1>
        <p>Sign in to manage stores, Stripe keys, payouts, and analytics.</p>
        <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>Email<input name="email" type="email" value="admin@storehub.local" required></label>
            <label>Password<input name="password" type="password" value="password" required></label>
            <button class="btn primary" type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
