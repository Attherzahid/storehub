<?php

use App\Models\Dashboard;

$metrics = Dashboard::metrics();
$charts = Dashboard::charts();
$transactions = Dashboard::recentTransactions();
$activity = Dashboard::activity();
$payouts = Dashboard::payouts();
?>
<section class="metric-grid">
    <article class="metric-card"><span>Total monthly sales</span><strong>$<?= number_format($metrics['monthly_sales'], 2) ?></strong><i class="fa-solid fa-dollar-sign"></i></article>
    <article class="metric-card"><span>Connected stores</span><strong><?= number_format($metrics['stores']) ?></strong><i class="fa-solid fa-store"></i></article>
    <article class="metric-card"><span>Stripe keys</span><strong><?= number_format($metrics['keys']) ?></strong><i class="fa-solid fa-key"></i></article>
    <article class="metric-card"><span>Active stores</span><strong><?= number_format($metrics['active_stores']) ?></strong><i class="fa-solid fa-signal"></i></article>
    <article class="metric-card danger"><span>Failed payments</span><strong><?= number_format($metrics['failed_payments']) ?></strong><i class="fa-solid fa-triangle-exclamation"></i></article>
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
    <article class="panel">
        <div class="panel-head"><h2>Top stores</h2></div>
        <canvas data-chart="doughnut" data-label="Sales" data-points='<?= e(json_encode($charts['topStores'])) ?>'></canvas>
    </article>
    <article class="panel span-2">
        <div class="panel-head"><h2>Revenue trend</h2></div>
        <canvas data-chart="line" data-label="Revenue" data-points='<?= e(json_encode($charts['revenueTrend'])) ?>'></canvas>
    </article>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-head"><h2>Latest payouts</h2></div>
        <div class="list">
            <?php foreach ($payouts as $payout): ?>
                <div class="list-row"><span><?= e($payout['company_name']) ?></span><strong>$<?= number_format((float) $payout['amount'], 2) ?></strong><small><?= e($payout['payout_date']) ?></small></div>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="panel">
        <div class="panel-head"><h2>Recent activity</h2></div>
        <div class="timeline">
            <?php foreach ($activity as $item): ?>
                <div><span></span><p><?= e($item['message']) ?></p><small><?= e($item['created_at']) ?></small></div>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="panel span-2">
        <div class="panel-head"><h2>Recent Stripe transactions</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Store</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr><td><?= e($tx['store_name']) ?></td><td><?= e($tx['customer_email']) ?></td><td>$<?= number_format((float) $tx['amount'], 2) ?></td><td><span class="status <?= e($tx['status']) ?>"><?= e($tx['status']) ?></span></td><td><?= e($tx['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
