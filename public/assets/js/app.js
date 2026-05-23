const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const palette = ['#49d3ff', '#8b5cf6', '#37d399', '#fbbf24', '#fb7185', '#22c55e', '#f472b6'];

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
    button.addEventListener('click', () => document.getElementById(button.dataset.openModal)?.showModal());
});
document.querySelectorAll('[data-close-modal]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
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
