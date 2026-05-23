<?php

use App\Models\Store;
use App\Models\StripeKey;

$stores = Store::all($_GET['search'] ?? null);
$keys = StripeKey::all();
?>
<section class="panel">
    <div class="panel-head">
        <h2>Connected stores</h2>
        <button class="btn primary" data-open-modal="storeModal"><i class="fa-solid fa-plus"></i>Add store</button>
    </div>
    <form class="inline-filters" method="get">
        <input type="hidden" name="page" value="stores">
        <input name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search stores or domains">
        <button class="btn ghost">Filter</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Store</th><th>Sales</th><th>Orders</th><th>Stripe key</th><th>Status</th><th>Last sync</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($stores as $store): ?>
                    <tr>
                        <td><strong><?= e($store['name']) ?></strong><small><?= e($store['domain']) ?></small></td>
                        <td><?= e($store['currency']) ?> <?= number_format((float) $store['monthly_sales'], 2) ?></td>
                        <td><?= number_format((int) $store['order_count']) ?></td>
                        <td><?= e($store['stripe_company'] ?: 'Unassigned') ?></td>
                        <td><span class="status <?= e($store['status']) ?>"><?= e($store['status']) ?></span></td>
                        <td><?= e($store['last_sync_at']) ?></td>
                        <td class="row-actions"><button class="btn ghost" data-store-token="<?= (int) $store['id'] ?>">Token</button><button class="btn ghost" data-edit='<?= e(json_encode($store)) ?>'>Edit</button><button class="btn danger" data-delete="store" data-id="<?= (int) $store['id'] ?>">Delete</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal" id="storeModal">
    <form class="modal-card" data-ajax-form="store">
        <input type="hidden" name="id">
        <h2>Store details</h2>
        <div class="form-grid">
            <label>Name<input name="name" required></label>
            <label>Domain<input name="domain" required></label>
            <label>Stripe key<select name="stripe_key_id"><option value="">Unassigned</option><?php foreach ($keys as $key): ?><option value="<?= (int) $key['id'] ?>"><?= e($key['company_name']) ?></option><?php endforeach; ?></select></label>
            <label>Currency<input name="currency" value="USD"></label>
            <label>Status<select name="status"><option>active</option><option>disabled</option><option>syncing</option></select></label>
        </div>
        <div class="sync-note">
            <i class="fa-solid fa-rotate"></i>
            <div>
                <strong>Analytics sync automatically</strong>
                <p>Total sales, monthly sales, orders, average order value, WooCommerce version, WordPress version, and last sync time are filled by the WordPress plugin.</p>
            </div>
        </div>
        <div class="modal-actions"><button type="button" class="btn ghost" data-close-modal>Cancel</button><button class="btn primary">Save store</button></div>
    </form>
</dialog>
<dialog class="modal" id="tokenModal">
    <div class="modal-card">
        <h2>Store API token</h2>
        <p>Paste this token into the WordPress plugin settings. For security, it is shown only once.</p>
        <label>Token<input id="generatedStoreToken" readonly></label>
        <div class="sync-note">
            <i class="fa-solid fa-link"></i>
            <div>
                <strong>Plugin endpoint</strong>
                <p><?= e(url('api/store-sync.php')) ?></p>
            </div>
        </div>
        <div class="modal-actions"><button type="button" class="btn ghost" data-copy-token>Copy token</button><button type="button" class="btn primary" data-close-modal>Done</button></div>
    </div>
</dialog>
