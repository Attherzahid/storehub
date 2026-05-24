<?php

use App\Models\Dashboard;

$storeGroups = Dashboard::transactionsByStore();
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Payment history</p>
            <h2>Stripe transactions by store</h2>
        </div>
        <div class="panel-actions">
            <div class="filter-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input data-content-search data-search-target="#transactionStoreGroups" data-search-items=".store-transaction-panel" placeholder="Search stores or Stripe company">
            </div>
            <button class="btn ghost" data-export="csv"><i class="fa-solid fa-download"></i>CSV</button>
        </div>
    </div>
</section>

<section class="history-stack" id="transactionStoreGroups">
    <?php if (!$storeGroups): ?>
        <article class="panel"><p>No transactions recorded yet.</p></article>
    <?php endif; ?>
    <?php foreach ($storeGroups as $group): ?>
        <?php
        $store = $group['store'];
        $storeTransactions = $group['transactions'];
        $successfulAmount = 0.0;
        foreach ($storeTransactions as $transaction) {
            if ($transaction['status'] === 'succeeded') {
                $successfulAmount += (float) $transaction['amount'];
            }
        }
        ?>
        <article class="panel store-transaction-panel">
            <div class="panel-head">
                <div>
                    <h2><?= e($store['name']) ?></h2>
                    <p><?= e($store['domain'] ?: 'No store domain') ?> · Stripe: <?= e($store['stripe_company'] ?: 'Unassigned') ?></p>
                </div>
                <div class="store-transaction-totals">
                    <span><?= number_format(count($storeTransactions)) ?> transactions</span>
                    <strong><?= e($store['currency']) ?> <?= number_format($successfulAmount, 2) ?></strong>
                </div>
            </div>
            <div class="filter-box data-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input data-content-search data-search-target="#transactionTable<?= (int) ($storeTransactions[0]['store_id'] ?? 0) ?>" data-search-items="tbody tr" placeholder="Search transactions">
            </div>
            <div class="table-wrap">
                <table id="transactionTable<?= (int) ($storeTransactions[0]['store_id'] ?? 0) ?>">
                    <thead><tr><th>Transaction</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
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
