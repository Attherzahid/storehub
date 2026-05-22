<?php

use App\Models\StripeKey;

$keys = StripeKey::all($_GET['search'] ?? null);
?>
<section class="panel">
    <div class="panel-head">
        <h2>Stripe keys</h2>
        <button class="btn primary" data-open-modal="keyModal"><i class="fa-solid fa-plus"></i>Add key</button>
    </div>
    <form class="inline-filters" method="get">
        <input type="hidden" name="page" value="keys">
        <input name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search by company, email, or country">
        <button class="btn ghost">Filter</button>
    </form>
    <div class="card-grid">
        <?php foreach ($keys as $key): ?>
            <article class="data-card">
                <div class="card-top"><h3><?= e($key['company_name']) ?></h3><span class="status <?= e($key['status']) ?>"><?= e($key['status']) ?></span></div>
                <p><?= e($key['email']) ?> · <?= e($key['phone']) ?></p>
                <p><?= e($key['country_flag']) ?> <?= e($key['country_name']) ?> · <?= e($key['payout_timing']) ?></p>
                <div class="data-stats"><span>Volume <strong>$<?= number_format((float) $key['total_processed_volume'], 2) ?></strong></span><span>Stores <strong><?= e($key['connected_stores'] ?: 'None') ?></strong></span></div>
                <div class="row-actions">
                    <a class="btn ghost" href="index.php?page=key-details&id=<?= (int) $key['id'] ?>">More details</a>
                    <button class="btn ghost" data-edit='<?= e(json_encode($key)) ?>'>Edit</button>
                    <button class="btn danger" data-delete="stripe-key" data-id="<?= (int) $key['id'] ?>">Delete</button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<dialog class="modal" id="keyModal">
    <form class="modal-card" data-ajax-form="stripe-key">
        <input type="hidden" name="id">
        <h2>Stripe account</h2>
        <div class="form-grid">
            <label>Company<input name="company_name" required></label>
            <label>Email<input name="email" type="email" required></label>
            <label>Phone<input name="phone"></label>
            <label>Country<input name="country_name" list="countries" placeholder="Search country"></label>
            <label>Flag<input name="country_flag" placeholder="🇺🇸"></label>
            <label>Public key<input name="public_key" required></label>
            <label>Secret key<input name="secret_key" type="password" autocomplete="off"></label>
            <label>Account age<input name="account_age" placeholder="2 years"></label>
            <label>Payout timing<input name="payout_timing" placeholder="Rolling 2 days"></label>
            <label>Last payout<input name="last_payout_date" type="date"></label>
            <label>Total processed<input name="total_processed_volume" type="number" step="0.01"></label>
            <label>Status<select name="status"><option>active</option><option>disabled</option></select></label>
        </div>
        <div class="modal-actions"><button type="button" class="btn ghost" data-close-modal>Cancel</button><button class="btn primary">Save key</button></div>
    </form>
</dialog>
<datalist id="countries"><option>United States</option><option>United Kingdom</option><option>Canada</option><option>Australia</option><option>Pakistan</option><option>Germany</option><option>France</option><option>United Arab Emirates</option></datalist>
