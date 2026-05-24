<?php

use App\Models\StripeKey;

$details = StripeKey::details((int) ($_GET['id'] ?? 0));

if (!$details) {
    http_response_code(404);
    ?>
    <section class="empty-state">
        <h2>Stripe key not found</h2>
        <p>This key does not exist or was deleted.</p>
        <a class="btn ghost" href="index.php?page=keys">Back to keys</a>
    </section>
    <?php
    return;
}

$key = $details['key'];
$summary = $details['summary'];
$stores = $details['stores'];
$transactions = $details['transactions'];
$payouts = $details['payouts'];
$transactionsByStore = [];

foreach ($transactions as $transaction) {
    $storeId = (int) ($transaction['store_id'] ?? 0);
    $transactionsByStore[$storeId][] = $transaction;
}
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Stripe key history</p>
            <h2><?= e($key['company_name']) ?></h2>
            <p><?= e($key['email']) ?> &middot; <?= e($key['country_flag']) ?> <?= e($key['country_name']) ?> &middot; <?= e($key['payout_timing']) ?></p>
            <p><code><?= e($key['public_key']) ?></code> &middot; <code><?= e($key['secret_key_masked']) ?></code></p>
        </div>
        <a class="btn ghost" href="index.php?page=keys"><i class="fa-solid fa-arrow-left"></i>Back</a>
    </div>
    <section class="metric-grid compact">
        <article class="metric-card"><span>Connected stores</span><strong><?= number_format((int) $summary['connected_store_count']) ?></strong><i class="fa-solid fa-store"></i></article>
        <article class="metric-card"><span>Transactions</span><strong><?= number_format((int) $summary['transaction_count']) ?></strong><i class="fa-solid fa-receipt"></i></article>
        <article class="metric-card"><span>Successful volume</span><strong>$<?= number_format((float) $summary['successful_amount'], 2) ?></strong><i class="fa-solid fa-dollar-sign"></i></article>
        <article class="metric-card"><span>Success rate</span><strong><?= number_format((float) $summary['success_rate'], 1) ?>%</strong><i class="fa-solid fa-shield-halved"></i></article>
        <article class="metric-card danger"><span>Failed / refunds</span><strong><?= number_format((int) $summary['failed_count']) ?> / <?= number_format((int) $summary['refund_count']) ?></strong><i class="fa-solid fa-triangle-exclamation"></i></article>
    </section>
</section>

<section class="history-stack">
    <?php if (!$stores): ?>
        <article class="panel"><h2>No connected stores</h2><p>This Stripe key is not attached to any store yet.</p></article>
    <?php endif; ?>
    <?php foreach ($stores as $store): ?>
        <?php $storeTransactions = $transactionsByStore[(int) $store['id']] ?? []; ?>
        <article class="panel">
            <div class="panel-head">
                <div>
                    <h2><?= e($store['name']) ?></h2>
                    <p><?= e($store['domain']) ?> &middot; Last sync: <?= e($store['last_sync_at'] ?: 'Never') ?></p>
                </div>
                <span class="status <?= e($store['status']) ?>"><?= e($store['status']) ?></span>
            </div>
            <div class="store-history-summary">
                <span>Transactions <strong><?= number_format((int) $store['transaction_count']) ?></strong></span>
                <span>Successful amount <strong><?= e($store['currency']) ?> <?= number_format((float) $store['successful_amount'], 2) ?></strong></span>
                <span>Total amount <strong><?= e($store['currency']) ?> <?= number_format((float) $store['total_amount'], 2) ?></strong></span>
                <span>Failed <strong><?= number_format((int) $store['failed_count']) ?></strong></span>
                <span>Refunds <strong><?= number_format((int) $store['refund_count']) ?></strong></span>
            </div>
            <div class="filter-box data-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input data-content-search data-search-target="#keyStoreTransactions<?= (int) $store['id'] ?>" data-search-items="tbody tr" placeholder="Search transactions">
            </div>
            <div class="table-wrap">
                <table id="keyStoreTransactions<?= (int) $store['id'] ?>">
                    <thead><tr><th>Transaction</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if (!$storeTransactions): ?>
                            <tr><td colspan="5"><small>No transactions found for this store.</small></td></tr>
                        <?php endif; ?>
                        <?php foreach ($storeTransactions as $transaction): ?>
                            <tr>
                                <td><?= e($transaction['stripe_transaction_id'] ?: ('#' . $transaction['id'])) ?></td>
                                <td><?= e($transaction['customer_email'] ?: 'Unknown') ?></td>
                                <td><?= e($transaction['currency']) ?> <?= number_format((float) $transaction['amount'], 2) ?></td>
                                <td><span class="status <?= e($transaction['status']) ?>"><?= e($transaction['status']) ?></span></td>
                                <td><?= e($transaction['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-head"><h2>Payout history</h2></div>
        <div class="list">
            <?php if (!$payouts): ?><div class="list-row"><span>No payouts recorded</span></div><?php endif; ?>
            <?php foreach ($payouts as $payout): ?>
                <div class="list-row"><span><?= e($payout['payout_date']) ?></span><strong><?= e($payout['currency']) ?> <?= number_format((float) $payout['amount'], 2) ?></strong><small><?= e($payout['status']) ?></small></div>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="panel span-2">
        <div class="panel-head"><h2>Normal history log</h2></div>
        <div class="timeline">
            <?php if (!$transactions): ?><div><span></span><p>No transactions logged yet.</p></div><?php endif; ?>
            <?php foreach ($transactions as $transaction): ?>
                <div>
                    <span></span>
                    <p><?= e($transaction['store_name'] ?: 'Unassigned store') ?> · <?= e($transaction['currency']) ?> <?= number_format((float) $transaction['amount'], 2) ?> · <?= e($transaction['status']) ?></p>
                    <small><?= e($transaction['created_at']) ?> · <?= e($transaction['customer_email'] ?: 'Unknown customer') ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>
