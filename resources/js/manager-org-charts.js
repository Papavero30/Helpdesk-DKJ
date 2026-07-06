import Chart from 'chart.js/auto';

const PALETTE = {
    primary: '#0E4260',
    resolved: '#22c55e',
    donut: ['#0E4260', '#2563eb', '#14b8a6', '#f59e0b', '#a855f7', '#ef4444', '#ec4899', '#22c55e'],
};

const getStore = () => {
    if (!window.__managerOrgCharts) window.__managerOrgCharts = {};
    return window.__managerOrgCharts;
};

export const destroyManagerOrgCharts = () => {
    const store = getStore();
    Object.values(store).forEach((c) => c && typeof c.destroy === 'function' && c.destroy());
    window.__managerOrgCharts = {};
};

const safe = (arr) => (Array.isArray(arr) && arr.length ? arr : ['No data']);

export const initManagerOrgCharts = (data) => {
    if (!document.getElementById('orgPlantChart')) return;
    destroyManagerOrgCharts();

    const plant = data?.plant ?? { labels: [], handled: [], resolved: [] };
    const category = data?.category ?? { labels: [], handled: [] };

    const plantCtx = document.getElementById('orgPlantChart');
    if (plantCtx) {
        getStore().plant = new Chart(plantCtx, {
            type: 'bar',
            data: {
                labels: safe(plant.labels),
                datasets: [
                    { label: 'Handled', data: plant.handled ?? [], backgroundColor: PALETTE.primary, borderRadius: 4 },
                    { label: 'Resolved', data: plant.resolved ?? [], backgroundColor: PALETTE.resolved, borderRadius: 4 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
            },
        });
    }

    const catCtx = document.getElementById('orgCategoryChart');
    if (catCtx) {
        const labels = category.labels ?? [];
        const counts = category.handled ?? [];
        const hasData = labels.length > 0;
        getStore().category = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: hasData ? labels : ['No data'],
                datasets: [{
                    data: hasData ? counts : [1],
                    backgroundColor: hasData ? PALETTE.donut : ['#e5e7eb'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } },
                    tooltip: { callbacks: { label: (ctx) => hasData ? `${ctx.label}: ${ctx.parsed} ticket${ctx.parsed === 1 ? '' : 's'}` : 'No data' } },
                },
            },
        });
    }
};

window.__initManagerOrgCharts = initManagerOrgCharts;
window.__destroyManagerOrgCharts = destroyManagerOrgCharts;
window.dispatchEvent(new Event('manager-org-charts:ready'));
