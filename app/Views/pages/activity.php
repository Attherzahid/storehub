<?php

use App\Models\Dashboard;

$activity = Dashboard::activity(250);
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">System history</p>
            <h2>Recent activity</h2>
        </div>
        <div class="filter-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input data-content-search data-search-target="#activityLog" data-search-items=".activity-row" placeholder="Search activity">
        </div>
    </div>
    <div class="activity-log" id="activityLog">
        <?php if (!$activity): ?>
            <p>No activity has been recorded yet.</p>
        <?php endif; ?>
        <?php foreach ($activity as $item): ?>
            <article class="activity-row">
                <span class="activity-dot"></span>
                <div>
                    <p><?= e($item['message']) ?></p>
                    <small><?= e($item['created_at']) ?> · <?= e(ucfirst($item['type'])) ?><?= $item['ip_address'] ? ' · ' . e($item['ip_address']) : '' ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
