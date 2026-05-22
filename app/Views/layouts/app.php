<?php

$title = ucfirst($currentPage ?? 'Dashboard') . ' | Store Hub';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="app-shell">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <main class="content animate-rise">
            <?php require $viewFile; ?>
        </main>
    </div>
    <div class="toast-stack" id="toastStack"></div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
