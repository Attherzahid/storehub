<?php

use App\Models\StripeKey;

$keys = StripeKey::all();
$readyKeys = array_values(array_filter($keys, static fn (array $key): bool => $key['workflow_status'] === 'ready'));
$waitingKeys = array_values(array_filter($keys, static fn (array $key): bool => $key['workflow_status'] === 'payout_waiting'));

function payout_amount(array $key): string
{
    if ($key['last_payout_amount'] === null) {
        return 'No payout yet';
    }

    return e($key['last_payout_currency'] ?: 'USD') . ' ' . number_format((float) $key['last_payout_amount'], 2);
}

function wait_time(?string $date): string
{
    if (!$date) {
        return 'Due date not set';
    }

    $today = new DateTimeImmutable('today');
    $due = new DateTimeImmutable($date);
    $days = (int) $today->diff($due)->format('%r%a');

    if ($days === 0) {
        return 'Due today';
    }
    if ($days > 0) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' remaining';
    }

    $overdue = abs($days);
    return $overdue . ' day' . ($overdue === 1 ? '' : 's') . ' overdue';
}

function edit_key_payload(array $key): string
{
    unset($key['secret_key_encrypted'], $key['secret_key_masked'], $key['public_key_masked']);
    return e(json_encode($key));
}
?>
<section class="panel keys-head">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Stripe workflow</p>
            <h2>Keys management</h2>
        </div>
        <div class="row-actions">
            <button class="btn ghost" type="button" data-refresh-waiting><i class="fa-solid fa-rotate"></i>Refresh waiting payouts</button>
            <button class="btn primary" data-open-modal="keyModal"><i class="fa-solid fa-plus"></i>Add key</button>
        </div>
    </div>
    <div class="filter-box data-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input data-content-search data-search-target="#keyBoard" data-search-items=".key-workflow-card" placeholder="Search company, country, or connected store">
    </div>
</section>

<section class="key-board" id="keyBoard">
    <section class="workflow-column">
        <div class="workflow-column-head ready">
            <div>
                <h2>Ready to use</h2>
                <p>Available for assignment and sales targets</p>
            </div>
            <strong><?= count($readyKeys) ?></strong>
        </div>
        <div class="workflow-list">
            <?php if (!$readyKeys): ?><div class="empty-state"><p>No keys ready for use.</p></div><?php endif; ?>
            <?php foreach ($readyKeys as $key): ?>
                <?php $remaining = max(0, (float) $key['target_sales'] - (float) $key['cycle_sales']); ?>
                <article class="data-card key-workflow-card">
                    <div class="card-top"><h3><?= e($key['company_name']) ?></h3><span class="status active">ready</span></div>
                    <p><?= e($key['email']) ?> &middot; <?= e($key['country_flag']) ?> <?= e($key['country_name']) ?></p>
                    <div class="credential-box">
                        <span>Public key<code><?= e($key['public_key_masked']) ?></code></span>
                        <span>Secret key<code><?= e($key['secret_key_masked']) ?></code></span>
                    </div>
                    <div class="key-facts">
                        <span>Last payout<strong><?= payout_amount($key) ?></strong></span>
                        <span>Sales target<strong>USD <?= number_format((float) $key['target_sales'], 2) ?></strong></span>
                        <span>Tracked sales<strong>USD <?= number_format((float) $key['cycle_sales'], 2) ?></strong></span>
                        <span>Remaining<strong>USD <?= number_format($remaining, 2) ?></strong></span>
                        <span>Connected stores<strong><?= e($key['connected_stores'] ?: 'None assigned') ?></strong></span>
                    </div>
                    <p class="workflow-note"><?= e($key['workflow_note'] ?: 'Ready for a store assignment.') ?></p>
                    <div class="row-actions">
                        <a class="btn ghost" href="index.php?page=key-details&id=<?= (int) $key['id'] ?>">Details</a>
                        <button class="btn ghost" data-refresh-payout="<?= (int) $key['id'] ?>"><i class="fa-solid fa-rotate"></i>Stripe</button>
                        <button class="btn ghost" data-reveal-key="<?= (int) $key['id'] ?>" data-company="<?= e($key['company_name']) ?>">Reveal</button>
                        <button class="btn primary" data-target-reached="<?= (int) $key['id'] ?>" data-company="<?= e($key['company_name']) ?>">Target reached</button>
                        <button class="btn ghost" data-edit='<?= edit_key_payload($key) ?>'>Edit</button>
                        <button class="btn danger" data-delete="stripe-key" data-id="<?= (int) $key['id'] ?>">Delete</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="workflow-column waiting-column">
        <div class="workflow-column-head waiting">
            <div>
                <h2>Payout waiting</h2>
                <p>Sorted by the nearest payout due date</p>
            </div>
            <strong><?= count($waitingKeys) ?></strong>
        </div>
        <div class="workflow-list">
            <?php if (!$waitingKeys): ?><div class="empty-state"><p>No keys waiting for payout.</p></div><?php endif; ?>
            <?php foreach ($waitingKeys as $key): ?>
                <article class="data-card key-workflow-card waiting-card">
                    <div class="card-top"><h3><?= e($key['company_name']) ?></h3><span class="status syncing">waiting</span></div>
                    <p><?= e($key['email']) ?> &middot; <?= e($key['country_flag']) ?> <?= e($key['country_name']) ?></p>
                    <div class="credential-box">
                        <span>Public key<code><?= e($key['public_key_masked']) ?></code></span>
                        <span>Secret key<code><?= e($key['secret_key_masked']) ?></code></span>
                    </div>
                    <div class="key-facts">
                        <span>Last payout<strong><?= payout_amount($key) ?></strong></span>
                        <span>Target submitted<strong>USD <?= number_format((float) $key['target_sales'], 2) ?></strong></span>
                        <span>Tracked sales<strong>USD <?= number_format((float) $key['cycle_sales'], 2) ?></strong></span>
                        <span>Payout schedule<strong><?= e($key['payout_timing'] ?: 'Not available') ?></strong></span>
                        <span>Expected arrival<strong><?= e($key['payout_due_date'] ?: 'Not scheduled') ?></strong></span>
                        <span>Payout wait time<strong><?= e(wait_time($key['payout_due_date'])) ?></strong></span>
                        <span>Stripe payout status<strong><?= e($key['stripe_payout_status'] ?: 'Not refreshed') ?></strong></span>
                        <span>Payout received<strong><?= ($key['stripe_payout_status'] ?? '') === 'paid' ? 'Yes' : 'No' ?></strong></span>
                        <span>Last Stripe refresh<strong><?= e($key['stripe_payout_synced_at'] ?: 'Never') ?></strong></span>
                    </div>
                    <p class="workflow-note"><?= e($key['workflow_note']) ?></p>
                    <div class="row-actions">
                        <a class="btn ghost" href="index.php?page=key-details&id=<?= (int) $key['id'] ?>">Details</a>
                        <button class="btn primary" data-refresh-payout="<?= (int) $key['id'] ?>"><i class="fa-solid fa-rotate"></i>Refresh from Stripe</button>
                        <button class="btn ghost" data-record-payout="<?= (int) $key['id'] ?>" data-company="<?= e($key['company_name']) ?>">Record manually</button>
                        <button class="btn ghost" data-reveal-key="<?= (int) $key['id'] ?>" data-company="<?= e($key['company_name']) ?>">Reveal</button>
                        <button class="btn ghost" data-edit='<?= edit_key_payload($key) ?>'>Edit</button>
                        <button class="btn danger" data-delete="stripe-key" data-id="<?= (int) $key['id'] ?>">Delete</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
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
            <label>Flag<input name="country_flag" placeholder="Country flag"></label>
            <label>Public key<input name="public_key" required></label>
            <label>Secret key<input name="secret_key" type="password" autocomplete="off"></label>
            <label>Account age<input name="account_age" placeholder="2 years"></label>
            <label>Payout timing<input name="payout_timing" placeholder="Rolling 2 days"></label>
            <label>Last payout date<input name="last_payout_date" type="date"></label>
            <label>Existing transaction history (USD)<input name="baseline_volume" type="number" min="0" step="0.01" value="0"></label>
            <label>Status<select name="status"><option>active</option><option>disabled</option></select></label>
        </div>
        <div class="sync-note">
            <i class="fa-solid fa-route"></i>
            <div>
                <strong>Target setup</strong>
                <p>New accounts with zero history begin in payout waiting with a USD 5 verification target. Accounts with history begin ready with a target of 80% of the entered amount.</p>
            </div>
        </div>
        <div class="modal-actions"><button type="button" class="btn ghost" data-close-modal>Cancel</button><button class="btn primary">Save key</button></div>
    </form>
</dialog>

<dialog class="modal" id="targetReachedModal">
    <form class="modal-card" data-key-workflow="wait">
        <input type="hidden" name="key_id">
        <h2>Send to payout waiting</h2>
        <p data-workflow-company></p>
        <label>Fallback expected payout date<input name="payout_due_date" type="date"></label>
        <label>Assign linked stores to another ready key
            <select name="replacement_key_id">
                <option value="">Do not reassign now</option>
                <?php foreach ($readyKeys as $readyKey): ?>
                    <option value="<?= (int) $readyKey['id'] ?>"><?= e($readyKey['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="workflow-note">Stripe refresh will replace the fallback date with its payout arrival date. If this key is connected to a store, choosing a replacement moves those stores while payout is pending.</p>
        <div class="modal-actions"><button type="button" class="btn ghost" data-close-modal>Cancel</button><button class="btn primary">Wait for payout</button></div>
    </form>
</dialog>

<dialog class="modal" id="payoutReceivedModal">
    <form class="modal-card" data-key-workflow="payout">
        <input type="hidden" name="key_id">
        <h2>Confirm payout received</h2>
        <p data-workflow-company></p>
        <div class="form-grid">
            <label>Payout amount<input name="amount" type="number" min="0" step="0.01" required></label>
            <label>Currency<input name="currency" value="USD" required></label>
            <label>Payout received date<input name="payout_date" type="date" required></label>
        </div>
        <div class="modal-actions"><button type="button" class="btn ghost" data-close-modal>Cancel</button><button class="btn primary">Confirm and make ready</button></div>
    </form>
</dialog>

<dialog class="modal" id="revealKeyModal">
    <form class="modal-card" data-reveal-secret-form>
        <input type="hidden" name="id">
        <h2>Reveal secret key</h2>
        <p data-reveal-company></p>
        <label>Confirm your admin password<input name="password" type="password" autocomplete="current-password" required></label>
        <label class="revealed-secret" hidden>Secret key<input name="revealed_secret" readonly></label>
        <div class="modal-actions"><button type="button" class="btn ghost" data-close-modal>Cancel</button><button class="btn ghost" type="button" data-copy-secret hidden>Copy</button><button class="btn primary" type="submit">Reveal</button></div>
    </form>
</dialog>

<datalist id="countries"><option>United States</option><option>United Kingdom</option><option>Canada</option><option>Australia</option><option>Pakistan</option><option>Germany</option><option>France</option><option>United Arab Emirates</option></datalist>
