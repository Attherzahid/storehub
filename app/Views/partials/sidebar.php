<?php

$items = [
    'dashboard' => ['Dashboard', 'fa-chart-pie'],
    'keys' => ['Keys', 'fa-key'],
    'stores' => ['Stores', 'fa-store'],
    'analytics' => ['Analytics', 'fa-chart-line'],
    'settings' => ['Settings', 'fa-gear'],
];
?>
<aside class="sidebar" id="sidebar">
    <a href="index.php" class="logo">
        <span class="brand-mark">SH</span>
        <span class="logo-text">Store Hub</span>
    </a>
    <nav class="nav-menu">
        <?php foreach ($items as $key => [$label, $icon]): ?>
            <a class="nav-link <?= ($currentPage ?? '') === $key ? 'active' : '' ?>" href="index.php?page=<?= e($key) ?>">
                <i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Logout</span></a>
    </nav>
</aside>
