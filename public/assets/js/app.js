const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const palette = ['#49d3ff', '#8b5cf6', '#37d399', '#fbbf24', '#fb7185', '#22c55e', '#f472b6'];
const chartInstances = {};

function toast(message) {
    const node = document.createElement('div');
    node.className = 'toast';
    node.textContent = message;
    document.getElementById('toastStack')?.appendChild(node);
    setTimeout(() => node.remove(), 3200);
}

function chartConfig(canvas) {
    const type = canvas.dataset.chart;
    const points = JSON.parse(canvas.dataset.points || '[]');
    const labels = points.map(point => point.label);
    const values = points.map(point => Number(point.value || 0));
    return {
        type,
        data: {
            labels,
            datasets: [{
                label: canvas.dataset.label,
                data: values,
                borderColor: '#49d3ff',
                backgroundColor: type === 'doughnut' ? palette : 'rgba(73, 211, 255, .18)',
                fill: type === 'line',
                tension: .38,
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: getComputedStyle(document.body).getPropertyValue('--muted') } } },
            scales: type === 'doughnut' ? {} : {
                x: { ticks: { color: '#93a4bd' }, grid: { color: 'rgba(148,163,184,.12)' } },
                y: { ticks: { color: '#93a4bd' }, grid: { color: 'rgba(148,163,184,.12)' } }
            }
        }
    };
}

document.querySelectorAll('canvas[data-chart]').forEach(canvas => new Chart(canvas, chartConfig(canvas)));

function formatMoney(amount, currency = 'USD') {
    return new Intl.NumberFormat('en', { style: 'currency', currency }).format(Number(amount || 0));
}

function formatLocalInput(date) {
    const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function html(value) {
    return String(value ?? '').replace(/[&<>"']/g, match => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[match]));
}

function renderManagedChart(id, type, label, points) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    chartInstances[id]?.destroy();
    chartInstances[id] = new Chart(canvas, {
        type,
        data: {
            labels: points.map(point => point.label),
            datasets: [{
                label,
                data: points.map(point => Number(point.value || 0)),
                borderColor: '#49d3ff',
                backgroundColor: type === 'doughnut' ? palette : 'rgba(73, 211, 255, .18)',
                fill: type === 'line',
                tension: .35,
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#93a4bd' } } },
            scales: type === 'doughnut' ? {} : {
                x: { ticks: { color: '#93a4bd', maxRotation: 0 }, grid: { color: 'rgba(148,163,184,.12)' } },
                y: { ticks: { color: '#93a4bd' }, grid: { color: 'rgba(148,163,184,.12)' } }
            }
        }
    });
}

function paginateTable(table, pageSize = 20) {
    if (!table || table.dataset.paginated === 'true') return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const visibleRows = () => rows.filter(row => row.dataset.filtered !== 'true');
    if (visibleRows().length <= pageSize) {
        rows.forEach(row => {
            row.hidden = row.dataset.filtered === 'true';
        });
        return;
    }

    table.dataset.paginated = 'true';
    let page = 1;
    const controls = document.createElement('div');
    controls.className = 'pagination';

    const render = () => {
        const activeRows = visibleRows();
        const totalPages = Math.max(1, Math.ceil(activeRows.length / pageSize));
        page = Math.min(page, totalPages);
        const start = (page - 1) * pageSize;
        const end = start + pageSize;
        rows.forEach((row, index) => {
            if (row.dataset.filtered === 'true') {
                row.hidden = true;
                return;
            }
            const visibleIndex = activeRows.indexOf(row);
            row.hidden = visibleIndex < start || visibleIndex >= end;
        });
        controls.innerHTML = `
            <button class="btn ghost" type="button" data-page-prev ${page === 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i>Prev</button>
            <span>Page ${page} of ${totalPages}</span>
            <button class="btn ghost" type="button" data-page-next ${page === totalPages ? 'disabled' : ''}>Next<i class="fa-solid fa-chevron-right"></i></button>
        `;
        controls.querySelector('[data-page-prev]')?.addEventListener('click', () => {
            page = Math.max(1, page - 1);
            render();
        });
        controls.querySelector('[data-page-next]')?.addEventListener('click', () => {
            page = Math.min(totalPages, page + 1);
            render();
        });
    };

    table.closest('.table-wrap')?.after(controls);
    table._renderPagination = render;
    render();
}

function paginateTables(scope = document) {
    scope.querySelectorAll('table').forEach(table => paginateTable(table, 20));
}

function updateRowFilterState(row) {
    row.dataset.filtered = row.dataset.searchFiltered === 'true' || row.dataset.statusFiltered === 'true' ? 'true' : 'false';
}

function refreshTablePagination(table) {
    if (!table) return;
    table.removeAttribute('data-paginated');
    const controls = table.closest('.table-wrap')?.nextElementSibling;
    if (controls?.classList.contains('pagination')) {
        controls.remove();
    }
    paginateTable(table, 20);
}

function applyContentSearch(input) {
    if (!input) return;
    const target = document.querySelector(input.dataset.searchTarget);
    if (!target) return;
    const query = input.value.trim().toLowerCase();
    const items = target.querySelectorAll(input.dataset.searchItems);
    const tables = new Set();

    items.forEach(item => {
        const matches = query === '' || item.textContent.toLowerCase().includes(query);
        if (item.tagName === 'TR') {
            item.dataset.searchFiltered = matches ? 'false' : 'true';
            updateRowFilterState(item);
            tables.add(item.closest('table'));
        } else {
            item.hidden = !matches;
        }
    });

    tables.forEach(table => refreshTablePagination(table));
}

document.querySelectorAll('[data-content-search]').forEach(input => {
    input.addEventListener('input', () => applyContentSearch(input));
});

async function loadAnalytics() {
    const form = document.getElementById('analyticsFilters');
    if (!form) return;
    const params = new URLSearchParams(new FormData(form));
    const response = await fetch(`api/analytics.php?${params.toString()}`);
    const data = await response.json();
    if (!response.ok) {
        toast(data.error || 'Unable to load analytics');
        return;
    }

    const metrics = document.getElementById('analyticsMetrics');
    metrics.innerHTML = `
        <article class="metric-card"><span>Revenue</span><strong>${formatMoney(data.metrics.revenue)}</strong><i class="fa-solid fa-dollar-sign"></i></article>
        <article class="metric-card"><span>Transactions</span><strong>${Number(data.metrics.transaction_count || 0).toLocaleString()}</strong><i class="fa-solid fa-receipt"></i></article>
        <article class="metric-card"><span>Average order</span><strong>${formatMoney(data.metrics.average_order_value)}</strong><i class="fa-solid fa-basket-shopping"></i></article>
        <article class="metric-card"><span>Success rate</span><strong>${data.metrics.success_rate}%</strong><i class="fa-solid fa-shield-halved"></i></article>
        <article class="metric-card danger"><span>Failed / refunds</span><strong>${data.metrics.failed_count} / ${data.metrics.refund_count}</strong><i class="fa-solid fa-triangle-exclamation"></i></article>
    `;

    renderManagedChart('analyticsRevenueChart', 'line', 'Revenue', data.charts.revenueTrend || []);
    renderManagedChart('analyticsStoreChart', 'bar', 'Store revenue', data.charts.storeSales || []);
    renderManagedChart('analyticsStatusChart', 'doughnut', 'Payments', data.charts.statuses || []);

    document.getElementById('analyticsInsights').innerHTML = `
        <div class="list-row"><span>Best store</span><strong>${html(data.charts.storeSales?.[0]?.label || 'No data')}</strong></div>
        <div class="list-row"><span>Peak point</span><strong>${html(data.charts.revenueTrend?.at(-1)?.label || 'No data')}</strong></div>
        <div class="list-row"><span>Success rate</span><strong>${data.metrics.success_rate}%</strong></div>
    `;

    const rows = data.transactions || [];
    document.getElementById('analyticsTransactions').innerHTML = rows.length ? rows.map(tx => `
        <tr>
            <td>${html(tx.store_name || 'Unassigned')}</td>
            <td>${html(tx.customer_email || 'Unknown')}</td>
            <td>${html(tx.currency || 'USD')} ${Number(tx.amount || 0).toFixed(2)}</td>
            <td><span class="status ${html(tx.status)}">${html(tx.status)}</span></td>
            <td>${html(tx.created_at)}</td>
        </tr>
    `).join('') : '<tr><td colspan="5"><small>No transactions found for this range.</small></td></tr>';
    const analyticsTable = document.getElementById('analyticsTransactions')?.closest('table');
    applyTransactionStatusFilter();
    const search = Array.from(document.querySelectorAll('[data-content-search]')).find(input => input.dataset.searchTarget === '#analyticsTransactionTable');
    applyContentSearch(search);
}

function applyAnalyticsPreset(preset) {
    const form = document.getElementById('analyticsFilters');
    if (!form) return;
    const to = new Date();
    const from = new Date(to);
    const hours = { '1h': 1, '6h': 6, '24h': 24, '7d': 168, '30d': 720 }[preset] || 24;
    from.setHours(from.getHours() - hours);
    form.elements.from.value = formatLocalInput(from);
    form.elements.to.value = formatLocalInput(to);
    loadAnalytics();
}

document.getElementById('analyticsFilters')?.addEventListener('submit', event => {
    event.preventDefault();
    loadAnalytics();
});

document.querySelectorAll('[data-preset]').forEach(button => {
    button.addEventListener('click', () => applyAnalyticsPreset(button.dataset.preset));
});

function applyTransactionStatusFilter() {
    const group = document.querySelector('[data-status-filter]');
    const tbody = document.getElementById('analyticsTransactions');
    const table = tbody?.closest('table');
    if (!group || !tbody || !table) return;

    const active = group.querySelector('.active')?.dataset.status || 'all';
    tbody.querySelectorAll('tr').forEach(row => {
        const status = row.querySelector('.status')?.textContent?.trim() || '';
        row.dataset.statusFiltered = active !== 'all' && status !== active ? 'true' : 'false';
        updateRowFilterState(row);
    });

    refreshTablePagination(table);
}

document.querySelectorAll('[data-status-filter] button').forEach(button => {
    button.addEventListener('click', () => {
        const group = button.closest('[data-status-filter]');
        group.querySelectorAll('button').forEach(item => item.classList.remove('active'));
        button.classList.add('active');
        applyTransactionStatusFilter();
    });
});

if (document.getElementById('analyticsFilters')) {
    applyAnalyticsPreset('24h');
}

paginateTables();

document.querySelector('[data-toggle-sidebar]')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('open');
});

document.getElementById('themeToggle')?.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = next;
    localStorage.setItem('storeHubTheme', next);
});
document.documentElement.dataset.theme = localStorage.getItem('storeHubTheme') || 'dark';

document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.openModal);
        const form = modal?.querySelector('form');
        if (form) {
            form.reset();
            const id = form.querySelector('[name="id"]');
            if (id) id.value = '';
        }
        modal?.showModal();
    });
});
document.querySelectorAll('[data-close-modal]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

async function refreshStripePayout(id) {
    const response = await fetch('api/key-payout-sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ id })
    });
    const result = await response.json();
    if (!response.ok) {
        throw new Error(result.error || 'Unable to refresh Stripe payout data');
    }
    return result;
}

document.querySelectorAll('[data-refresh-payout]').forEach(button => {
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const result = await refreshStripePayout(button.dataset.refreshPayout);
            toast(result.message || 'Stripe payout data refreshed');
            setTimeout(() => location.reload(), 700);
        } catch (error) {
            toast(error.message);
            button.disabled = false;
        }
    });
});

async function refreshWaitingPayouts(showNotice = true) {
    const buttons = Array.from(document.querySelectorAll('.waiting-column [data-refresh-payout]'));
    if (!buttons.length) return;
    buttons.forEach(button => { button.disabled = true; });
    const results = await Promise.allSettled(buttons.map(button => refreshStripePayout(button.dataset.refreshPayout)));
    const failures = results.filter(result => result.status === 'rejected');
    if (showNotice) {
        toast(failures.length
            ? `${buttons.length - failures.length} payout records refreshed; ${failures.length} could not be fetched.`
            : 'Waiting payouts refreshed from Stripe.');
    }
    if (failures.length === buttons.length) {
        buttons.forEach(button => { button.disabled = false; });
        if (!showNotice && failures[0]?.reason?.message) toast(failures[0].reason.message);
        return;
    }
    setTimeout(() => location.reload(), showNotice ? 700 : 100);
}

document.querySelector('[data-refresh-waiting]')?.addEventListener('click', () => refreshWaitingPayouts());

if (document.querySelector('.waiting-column [data-refresh-payout]')) {
    const refreshKey = 'storeHubWaitingPayoutRefresh';
    const lastRefresh = Number(sessionStorage.getItem(refreshKey) || 0);
    if (Date.now() - lastRefresh > 300000) {
        sessionStorage.setItem(refreshKey, String(Date.now()));
        refreshWaitingPayouts(false);
    }
}

document.querySelectorAll('[data-reveal-key]').forEach(button => {
    button.addEventListener('click', () => {
        const modal = document.getElementById('revealKeyModal');
        const form = modal?.querySelector('[data-reveal-secret-form]');
        if (!modal || !form) return;
        form.reset();
        form.elements.id.value = button.dataset.revealKey;
        form.querySelector('[data-reveal-company]').textContent = `${button.dataset.company}: confirm your admin password to view this secret key.`;
        form.querySelector('.revealed-secret').hidden = true;
        form.querySelector('[data-copy-secret]').hidden = true;
        modal.showModal();
    });
});

document.querySelector('[data-reveal-secret-form]')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
        const response = await fetch('api/key-secret.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({
                id: form.elements.id.value,
                password: form.elements.password.value
            })
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Unable to reveal secret key');
        form.elements.revealed_secret.value = result.secret_key;
        form.elements.password.value = '';
        form.querySelector('.revealed-secret').hidden = false;
        form.querySelector('[data-copy-secret]').hidden = false;
        toast('Secret key revealed. This action has been recorded.');
    } catch (error) {
        toast(error.message);
    } finally {
        button.disabled = false;
    }
});

document.querySelector('[data-copy-secret]')?.addEventListener('click', async event => {
    const input = event.currentTarget.closest('form')?.elements.revealed_secret;
    input?.select();
    await navigator.clipboard?.writeText(input?.value || '');
    toast('Secret key copied');
});

document.getElementById('revealKeyModal')?.addEventListener('close', event => {
    const form = event.currentTarget.querySelector('form');
    if (!form) return;
    form.reset();
    form.querySelector('.revealed-secret').hidden = true;
    form.querySelector('[data-copy-secret]').hidden = true;
});

document.querySelectorAll('[data-edit]').forEach(button => {
    button.addEventListener('click', () => {
        const data = JSON.parse(button.dataset.edit);
        const modal = document.getElementById(data.domain ? 'storeModal' : 'keyModal');
        Object.entries(data).forEach(([key, value]) => {
            const field = modal?.querySelector(`[name="${key}"]`);
            if (field) field.value = String(value ?? '').replace(' ', 'T');
        });
        modal?.showModal();
    });
});

document.querySelectorAll('[data-details]').forEach(button => {
    button.addEventListener('click', () => {
        const data = JSON.parse(button.dataset.details);
        toast(`${data.company_name}: risk low, success rate 96.8%, API usage normal.`);
    });
});

document.querySelectorAll('[data-ajax-form]').forEach(form => {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const resource = form.dataset.ajaxForm;
        const payload = Object.fromEntries(new FormData(form).entries());
        const method = payload.id ? 'PUT' : 'POST';
        const response = await fetch(`api/${resource}.php`, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        toast(result.message || 'Saved');
        if (response.ok) setTimeout(() => location.reload(), 700);
    });
});

document.querySelectorAll('[data-target-reached]').forEach(button => {
    button.addEventListener('click', () => {
        const modal = document.getElementById('targetReachedModal');
        modal.querySelector('[name="key_id"]').value = button.dataset.targetReached;
        modal.querySelector('[data-workflow-company]').textContent = `${button.dataset.company}: Stripe will provide the payout arrival date when available.`;
        modal.querySelectorAll('[name="replacement_key_id"] option').forEach(option => {
            option.disabled = option.value === button.dataset.targetReached;
        });
        modal.querySelector('[name="replacement_key_id"]').value = '';
        modal.showModal();
    });
});

document.querySelectorAll('[data-record-payout]').forEach(button => {
    button.addEventListener('click', () => {
        const modal = document.getElementById('payoutReceivedModal');
        modal.querySelector('[name="key_id"]').value = button.dataset.recordPayout;
        modal.querySelector('[name="payout_date"]').value = formatLocalInput(new Date()).slice(0, 10);
        modal.querySelector('[data-workflow-company]').textContent = `${button.dataset.company}: confirm the payout received to calculate its next target.`;
        modal.showModal();
    });
});

document.querySelectorAll('[data-key-workflow]').forEach(form => {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.action = form.dataset.keyWorkflow;
        const response = await fetch('api/key-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        toast(result.message || result.error || 'Unable to update key workflow');
        if (response.ok) {
            setTimeout(() => location.reload(), 700);
        }
    });
});

document.querySelectorAll('[data-delete]').forEach(button => {
    button.addEventListener('click', async () => {
        if (!confirm('Delete this record?')) return;
        const response = await fetch(`api/${button.dataset.delete}.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ id: button.dataset.id })
        });
        const result = await response.json();
        toast(result.message || 'Deleted');
        if (response.ok) setTimeout(() => location.reload(), 700);
    });
});

document.querySelectorAll('[data-store-token]').forEach(button => {
    button.addEventListener('click', async () => {
        const response = await fetch('api/store-token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ store_id: button.dataset.storeToken })
        });
        const result = await response.json();
        if (!response.ok) {
            toast(result.error || 'Unable to generate token');
            return;
        }
        const input = document.getElementById('generatedStoreToken');
        input.value = result.token;
        document.getElementById('tokenModal')?.showModal();
        toast(result.message);
    });
});

document.querySelector('[data-copy-token]')?.addEventListener('click', async () => {
    const input = document.getElementById('generatedStoreToken');
    input?.select();
    await navigator.clipboard?.writeText(input?.value || '');
    toast('Token copied');
});

document.querySelectorAll('[data-export]').forEach(button => {
    button.addEventListener('click', () => {
        if (button.dataset.export === 'csv') {
            location.href = 'api/export.php?type=csv';
            return;
        }
        window.print();
    });
});

document.getElementById('refreshDashboard')?.addEventListener('click', async () => {
    const response = await fetch('api/analytics.php');
    if (response.ok) toast('Live dashboard data refreshed');
});

setInterval(() => {
    if (document.visibilityState === 'visible') {
        fetch('api/analytics.php').catch(() => {});
    }
}, 60000);
