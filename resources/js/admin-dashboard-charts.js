import Chart from 'chart.js/auto';

const PALETTE = {
    primary: '#0E4260',
    resolved: '#22c55e',
    bars: ['#0E4260', '#2563eb', '#14b8a6', '#f59e0b', '#a855f7', '#ef4444', '#ec4899', '#22c55e'],
};

const getStore = () => {
    if (!window.__adminDashCharts) window.__adminDashCharts = {};
    return window.__adminDashCharts;
};

const destroyAdminDashCharts = () => {
    const store = getStore();
    Object.values(store).forEach((c) => c && typeof c.destroy === 'function' && c.destroy());
    window.__adminDashCharts = {};
};

const makeChart = (id, config) => {
    const el = document.getElementById(id);
    if (!el) return;
    getStore()[id] = new Chart(el, config);
};

const safe = (arr) => (Array.isArray(arr) && arr.length ? arr : ['No data']);

export const initAdminDashCharts = (data) => {
    if (!document.getElementById('adminMyTrend')) return;
    destroyAdminDashCharts();

    const tr = data?.trend ?? { labels: [], created: [], resolved: [] };
    const cat = data?.categories ?? { labels: [], handled: [] };

    makeChart('adminMyTrend', {
        type: 'line',
        data: {
            labels: safe(tr.labels),
            datasets: [
                { label: 'Created', data: tr.created ?? [], borderColor: PALETTE.primary, backgroundColor: 'rgba(14,66,96,0.1)', tension: 0.3, fill: true },
                { label: 'Resolved', data: tr.resolved ?? [], borderColor: PALETTE.resolved, backgroundColor: 'rgba(34,197,94,0.1)', tension: 0.3, fill: true },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });

    makeChart('adminMyCategories', {
        type: 'bar',
        data: {
            labels: safe(cat.labels),
            datasets: [{ label: 'Handled', data: cat.handled ?? [], backgroundColor: PALETTE.bars }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
};

window.__initAdminDashCharts = initAdminDashCharts;
window.__destroyAdminDashCharts = destroyAdminDashCharts;
window.dispatchEvent(new Event('admin-dashboard-charts:ready'));
