<?php

use App\Models\Dashboard;

$metrics = Dashboard::metrics();
$charts = Dashboard::charts();
$storesOverview = Dashboard::storesOverview();
$payouts = Dashboard::payouts();
?>
<section class="metric-grid dashboard-metrics">
    <article class="metric-card"><span>Connected stores</span><strong><?= number_format($metrics['stores']) ?></strong><i class="fa-solid fa-store"></i></article>
    <article class="metric-card"><span>Stripe keys</span><strong><?= number_format($metrics['keys']) ?></strong><i class="fa-solid fa-key"></i></article>
    <article class="metric-card"><span>Active stores</span><strong><?= number_format($metrics['active_stores']) ?></strong><i class="fa-solid fa-signal"></i></article>
    <article class="metric-card danger"><span>Failed payments</span><strong><?= number_format($metrics['failed_payments']) ?></strong><i class="fa-solid fa-triangle-exclamation"></i></article>
</section>

<section class="panel stores-overview">
    <div class="panel-head">
        <h2>Store sales overview</h2>
        <div class="panel-actions">
            <div class="filter-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input data-content-search data-search-target="#storeOverviewGrid" data-search-items=".store-overview-card" placeholder="Search stores or Stripe company">
            </div>
            <a class="btn ghost" href="index.php?page=stores">Manage stores</a>
        </div>
    </div>
    <div class="store-overview-grid" id="storeOverviewGrid">
        <?php if (!$storesOverview): ?>
            <div class="empty-state"><p>No stores connected yet.</p></div>
        <?php endif; ?>
        <?php foreach ($storesOverview as $store): ?>
            <article class="store-overview-card">
                <div class="card-top">
                    <div>
                        <h3><?= e($store['name']) ?></h3>
                        <small><?= e($store['domain']) ?></small>
                    </div>
                    <span class="status <?= e($store['status']) ?>"><?= e($store['status']) ?></span>
                </div>
                <div class="stripe-assignment">
                    <i class="fa-solid fa-key"></i>
                    <div>
                        <small>Activated Stripe company</small>
                        <strong><?= e($store['stripe_company'] ?: 'No Stripe key assigned') ?></strong>
                    </div>
                    <?php if ($store['stripe_company']): ?>
                        <span class="status <?= e($store['stripe_status']) ?>"><?= e($store['stripe_status']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="store-sales">
                    <div>
                        <small>Total sales</small>
                        <strong><?= e($store['currency']) ?> <?= number_format((float) $store['total_sales'], 2) ?></strong>
                    </div>
                    <div>
                        <small>This month</small>
                        <strong><?= e($store['currency']) ?> <?= number_format((float) $store['monthly_sales'], 2) ?></strong>
                    </div>
                    <div class="store-transactions">
                        <small>Transactions</small>
                        <strong><?= number_format((int) $store['transaction_count']) ?></strong>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-grid">
    <article class="panel span-2">
        <div class="panel-head"><h2>Monthly sales</h2><button class="btn ghost" data-export="csv">Export CSV</button></div>
        <canvas data-chart="line" data-label="Revenue" data-points='<?= e(json_encode($charts['monthlySales'])) ?>'></canvas>
    </article>
    <article class="panel">
        <div class="panel-head"><h2>Store comparison</h2></div>
        <canvas data-chart="bar" data-label="Sales" data-points='<?= e(json_encode($charts['storeSales'])) ?>'></canvas>
    </article>
</section>

<section>
    <article class="panel payout-panel">
        <div class="panel-head">
            <h2>Latest payouts</h2>
            <div class="filter-box compact-filter">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input data-content-search data-search-target="#latestPayouts" data-search-items=".list-row" placeholder="Search payouts">
            </div>
        </div>
        <div class="list" id="latestPayouts">
            <?php foreach ($payouts as $payout): ?>
                <div class="list-row"><span><?= e($payout['company_name']) ?></span><strong>$<?= number_format((float) $payout['amount'], 2) ?></strong><small><?= e($payout['payout_date']) ?></small></div>
            <?php endforeach; ?>
        </div>
    </article>
</section>
