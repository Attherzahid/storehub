<?php

use App\Models\Store;

$stores = Store::all();
?>
<section class="panel analytics-page">
    <div class="panel-head">
        <h2>Analytics studio</h2>
        <div class="row-actions"><button class="btn ghost" data-export="csv">CSV</button><button class="btn ghost" data-export="pdf">PDF</button></div>
    </div>
    <form class="analytics-filters" id="analyticsFilters">
        <label>Store
            <select name="store_id">
                <option value="">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?= (int) $store['id'] ?>"><?= e($store['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>From<input name="from" type="datetime-local"></label>
        <label>To<input name="to" type="datetime-local"></label>
        <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i>Apply</button>
    </form>
    <div class="preset-row" data-analytics-presets>
        <button class="btn ghost" data-preset="1h" type="button">Previous hour</button>
        <button class="btn ghost" data-preset="6h" type="button">Previous 6 hours</button>
        <button class="btn ghost" data-preset="24h" type="button">Previous 24 hours</button>
        <button class="btn ghost" data-preset="7d" type="button">Previous week</button>
        <button class="btn ghost" data-preset="30d" type="button">Previous month</button>
    </div>
</section>

<section class="metric-grid compact" id="analyticsMetrics">
    <article class="metric-card"><span>Revenue</span><strong>$0.00</strong><i class="fa-solid fa-dollar-sign"></i></article>
    <article class="metric-card"><span>Transactions</span><strong>0</strong><i class="fa-solid fa-receipt"></i></article>
    <article class="metric-card"><span>Average order</span><strong>$0.00</strong><i class="fa-solid fa-basket-shopping"></i></article>
    <article class="metric-card"><span>Success rate</span><strong>0%</strong><i class="fa-solid fa-shield-halved"></i></article>
    <article class="metric-card danger"><span>Failed / refunds</span><strong>0 / 0</strong><i class="fa-solid fa-triangle-exclamation"></i></article>
</section>

<section class="dashboard-grid">
    <article class="panel span-2">
        <div class="panel-head"><h2>Revenue trend</h2></div>
        <canvas id="analyticsRevenueChart"></canvas>
    </article>
    <article class="panel">
        <div class="panel-head"><h2>Payment status</h2></div>
        <canvas id="analyticsStatusChart"></canvas>
    </article>
    <article class="panel span-2">
        <div class="panel-head"><h2>Store comparison</h2></div>
        <canvas id="analyticsStoreChart"></canvas>
    </article>
    <article class="panel">
        <div class="panel-head"><h2>Insight summary</h2></div>
        <div class="list" id="analyticsInsights"></div>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Transaction log</h2>
        <div class="panel-actions">
            <div class="filter-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input data-content-search data-search-target="#analyticsTransactionTable" data-search-items="tbody tr" placeholder="Search transactions">
            </div>
            <div class="segmented-control" data-status-filter>
                <button class="active" type="button" data-status="all">All</button>
                <button type="button" data-status="succeeded">Succeeded</button>
                <button type="button" data-status="pending">Pending</button>
                <button type="button" data-status="failed">Failed</button>
                <button type="button" data-status="refunded">Refunded</button>
            </div>
        </div>
    </div>
    <div class="table-wrap">
        <table id="analyticsTransactionTable">
            <thead><tr><th>Store</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody id="analyticsTransactions"></tbody>
        </table>
    </div>
</section>
