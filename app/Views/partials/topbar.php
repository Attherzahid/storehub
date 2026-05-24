<header class="topbar">
    <button class="icon-btn mobile-only" data-toggle-sidebar aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
    <div>
        <p class="eyebrow">Control center</p>
        <h1><?= e(ucfirst($currentPage ?? 'Dashboard')) ?></h1>
    </div>
    <div class="top-actions">
        <button class="icon-btn" id="themeToggle" aria-label="Toggle theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
        <button class="icon-btn" id="refreshDashboard" aria-label="Refresh data"><i class="fa-solid fa-rotate"></i></button>
        <div class="user-chip"><?= e($user['name'] ?? 'Admin') ?></div>
    </div>
</header>
