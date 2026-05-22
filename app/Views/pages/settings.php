<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-head"><h2>Admin settings</h2></div>
        <form class="stack" data-settings-form>
            <label>Dashboard name<input value="Store Hub"></label>
            <label>Default currency<select><option>USD</option><option>GBP</option><option>EUR</option></select></label>
            <label>Live refresh interval<input type="number" value="60"></label>
            <label class="toggle-row"><span>Email notifications</span><input type="checkbox" checked></label>
            <label class="toggle-row"><span>Require API token rotation</span><input type="checkbox" checked></label>
            <button class="btn primary">Save settings</button>
        </form>
    </article>
    <article class="panel">
        <div class="panel-head"><h2>Security</h2></div>
        <div class="list">
            <div class="list-row"><span>CSRF protection</span><strong>Enabled</strong></div>
            <div class="list-row"><span>Prepared statements</span><strong>Enabled</strong></div>
            <div class="list-row"><span>Secret key encryption</span><strong>AES-256-CBC</strong></div>
            <div class="list-row"><span>API token hashing</span><strong>SHA-256</strong></div>
        </div>
    </article>
</section>
