import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

const normalizeSeries = (labels, counts) => {
    if (!Array.isArray(labels) || !Array.isArray(counts) || labels.length === 0 || counts.length === 0) {
        return { labels: ['No Data'], counts: [0] };
    }
    return { labels, counts };
};

const getChartStore = () => {
    if (!window.__karyawanDashboardCharts) {
        window.__karyawanDashboardCharts = {};
    }
    return window.__karyawanDashboardCharts;
};

export const destroyKaryawanDashboardCharts = () => {
    const store = getChartStore();
    Object.values(store).forEach((chart) => {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
    });
    window.__karyawanDashboardCharts = {};
};

export const initKaryawanDashboardCharts = (data) => {
    const root = document.getElementById('monthlyFrequencyChart');
    if (!root) {
        return;
    }

    destroyKaryawanDashboardCharts();

    // Caller is responsible for handing in plain-data (deep-cloned out of any Livewire proxy).
    const safe = data ?? {};
    const frequencyData = safe.frequency ?? { labels: [], counts: [], granularity: 'daily' };
    const kategoriData = safe.kategori ?? [];
    const slaOutcomeData = safe.sla_outcome ?? { on_time: 0, ahead: 0, overtime: 0 };

    // ── Frequency line ────────────────────────────────────────────────
    const frequencyCtx = document.getElementById('monthlyFrequencyChart');
    if (frequencyCtx) {
        const normalized = normalizeSeries(frequencyData.labels, frequencyData.counts);
        const isWeekly = frequencyData.granularity === 'weekly';

        getChartStore().monthlyFrequency = new Chart(frequencyCtx, {
            type: 'line',
            data: {
                labels: normalized.labels,
                datasets: [
                    {
                        label: isWeekly ? 'Tickets per week' : 'Tickets per day',
                        data: normalized.counts,
                        borderColor: '#0E4260',
                        backgroundColor: 'rgba(14,66,96,0.15)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#0E4260',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.parsed.y} ticket${ctx.parsed.y === 1 ? '' : 's'}`,
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    // ── Kategori donut ────────────────────────────────────────────────
    const kategoriCtx = document.getElementById('kategoriChart');
    if (kategoriCtx) {
        const labels = kategoriData.map((item) => item.label);
        const counts = kategoriData.map((item) => item.count);
        const colors = kategoriData.map((item) => item.color);
        const normalized = normalizeSeries(labels, counts);

        getChartStore().kategori = new Chart(kategoriCtx, {
            type: 'doughnut',
            data: {
                labels: normalized.labels,
                datasets: [
                    {
                        data: normalized.counts,
                        backgroundColor: colors.length ? colors : ['#cbd5e1'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 10, font: { size: 11 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ${ctx.parsed} ticket${ctx.parsed === 1 ? '' : 's'}`,
                        },
                    },
                },
            },
        });
    }

    // ── SLA Outcome donut (3 distinct buckets matching the sla_outcome enum) ──
    const slaCtx = document.getElementById('slaOutcomeChart');
    if (slaCtx) {
        const onTime = Number(slaOutcomeData.on_time ?? 0);
        const ahead = Number(slaOutcomeData.ahead ?? 0);
        const overtime = Number(slaOutcomeData.overtime ?? 0);
        const total = onTime + ahead + overtime;

        const labels = ['On Time', 'Ahead of Schedule', 'Over Time'];
        const counts = total === 0 ? [1] : [onTime, ahead, overtime];
        const colors = total === 0
            ? ['#e5e7eb']
            : ['#22c55e', '#14b8a6', '#ef4444'];
        const segmentLabels = total === 0 ? ['No Data'] : labels;

        getChartStore().slaOutcome = new Chart(slaCtx, {
            type: 'doughnut',
            data: {
                labels: segmentLabels,
                datasets: [
                    {
                        data: counts,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 10, font: { size: 11 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => total === 0
                                ? 'No Data'
                                : `${ctx.label}: ${ctx.parsed} ticket${ctx.parsed === 1 ? '' : 's'}`,
                        },
                    },
                },
            },
        });
    }
};

window.__initKaryawanDashboardCharts = initKaryawanDashboardCharts;
window.__destroyKaryawanDashboardCharts = destroyKaryawanDashboardCharts;
window.dispatchEvent(new Event('karyawan-dashboard-charts:ready'));
