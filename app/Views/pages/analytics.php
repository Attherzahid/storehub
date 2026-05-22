<?php

use App\Models\Dashboard;

$charts = Dashboard::charts();
?>
<section class="panel">
    <div class="panel-head">
        <h2>Analytics studio</h2>
        <div class="row-actions"><button class="btn ghost" data-export="csv">CSV</button><button class="btn ghost" data-export="pdf">PDF</button></div>
    </div>
    <div class="dashboard-grid">
        <article class="mini-panel"><h3>Best performing store</h3><strong><?= e($charts['topStores'][0]['label'] ?? 'No data') ?></strong></article>
        <article class="mini-panel"><h3>Most used Stripe key</h3><strong>Northstar Payments</strong></article>
        <article class="mini-panel"><h3>Payment success rate</h3><strong>96.8%</strong></article>
    </div>
    <div class="dashboard-grid">
        <article class="panel span-2"><canvas data-chart="bar" data-label="Store revenue" data-points='<?= e(json_encode($charts['storeSales'])) ?>'></canvas></article>
        <article class="panel"><canvas data-chart="line" data-label="Revenue trend" data-points='<?= e(json_encode($charts['revenueTrend'])) ?>'></canvas></article>
    </div>
</section>
