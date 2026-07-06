import Chart from 'chart.js/auto';

const PALETTE = {
    primary: '#0E4260',
    resolved: '#2563eb',
    onTime: '#22c55e',
    ahead: '#0E4260',
    overtime: '#f59e0b',
    unresolved: '#e2e8f0',
    repetitive: '#a855f7',
    nonRepetitive: '#cbd5e1',
    created: '#0E4260',
};

const getStore = () => {
    if (!window.__reportCharts) window.__reportCharts = {};
    return window.__reportCharts;
};

const destroyReportCharts = () => {
    const store = getStore();
    Object.values(store).forEach((c) => c && typeof c.destroy === 'function' && c.destroy());
    window.__reportCharts = {};
};

const makeChart = (id, config) => {
    const el = document.getElementById(id);
    if (!el) return;
    const store = getStore();
    store[id] = new Chart(el, config);
};

const safe = (arr) => (Array.isArray(arr) && arr.length ? arr : ['No Data']);

export const initReportCharts = (data) => {
    if (!document.getElementById('reportHandledResolved')) return;
    destroyReportCharts();

    const hr = data?.handled_resolved ?? { labels: [], handled: [], resolved: [] };
    const rs = data?.resolution_status ?? { labels: [], on_time: [], ahead: [], overtime: [], unresolved: [] };
    const rep = data?.repetitive_share ?? { labels: [], repetitive: [], non_repetitive: [] };
    const tr = data?.trend ?? { labels: [], created: [], resolved: [] };

    makeChart('reportHandledResolved', {
        type: 'bar',
        data: {
            labels: safe(hr.labels),
            datasets: [
                { label: 'Handled', data: hr.handled ?? [], backgroundColor: PALETTE.primary },
                { label: 'Resolved', data: hr.resolved ?? [], backgroundColor: PALETTE.resolved },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });

    makeChart('reportResolutionStatus', {
        type: 'bar',
        data: {
            labels: safe(rs.labels),
            datasets: [
                { label: 'On-Time', data: rs.on_time ?? [], backgroundColor: PALETTE.onTime },
                { label: 'Ahead of Schedule', data: rs.ahead ?? [], backgroundColor: PALETTE.ahead },
                { label: 'Overtime', data: rs.overtime ?? [], backgroundColor: PALETTE.overtime },
                { label: 'Unresolved', data: rs.unresolved ?? [], backgroundColor: PALETTE.unresolved },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
    });

    makeChart('reportRepetitiveShare', {
        type: 'bar',
        data: {
            labels: safe(rep.labels),
            datasets: [
                { label: 'Repetitive', data: rep.repetitive ?? [], backgroundColor: PALETTE.repetitive },
                { label: 'Non-repetitive', data: rep.non_repetitive ?? [], backgroundColor: PALETTE.nonRepetitive },
            ],
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }, y: { stacked: true } } },
    });

    makeChart('reportTrend', {
        type: 'line',
        data: {
            labels: safe(tr.labels),
            datasets: [
                { label: 'Created', data: tr.created ?? [], borderColor: PALETTE.created, backgroundColor: 'rgba(14,66,96,0.1)', tension: 0.3, fill: true },
                { label: 'Resolved', data: tr.resolved ?? [], borderColor: PALETTE.onTime, backgroundColor: 'rgba(34,197,94,0.1)', tension: 0.3, fill: true },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
};

/**
 * Build the print-only document (table + charts) right before window.print().
 *
 * Why this exists: printing the live page failed — the app's fixed-height
 * scrollable layout clipped output to one page, and Chart.js <canvas> elements
 * frequently don't render in the print engine at all. Instead we snapshot each
 * chart to a PNG via Chart.js's toBase64Image(), clone the table, drop them into
 * a dedicated #report-print-root, and lift it to <body>. The print stylesheet
 * then hides everything except that root, so the PDF contains exactly the table
 * + all charts (and nothing else), flowing across as many pages as needed.
 */
const CHART_TITLES = {
    reportHandledResolved: 'Handled vs Resolved',
    reportResolutionStatus: 'Resolution Status',
    reportRepetitiveShare: 'Repetitive Share',
    reportTrend: 'Trend',
};

const removeReportPrintRoot = () => {
    const existing = document.getElementById('report-print-root');
    if (existing) existing.remove();
};

const buildReportPrint = () => {
    // Always start from a clean slate: a fresh root as a DIRECT child of <body>
    // (so `body > *:not(#report-print-root)` can isolate it). Created here rather
    // than in Blade so Livewire morphs can never produce a duplicate-id element.
    removeReportPrintRoot();

    const data = document.getElementById('report-print-data');
    const root = document.createElement('div');
    root.id = 'report-print-root';
    // .report-print-only keeps it hidden on screen (no flash); the @media print
    // rule (#report-print-root { display:block !important }) reveals it for print.
    root.className = 'report-print-only';
    document.body.appendChild(root);

    // Title + period (read from the hidden data holder in the component).
    const title = document.createElement('p');
    title.className = 'rp-title';
    title.textContent = data?.dataset.reportTitle || 'Performance Report';
    root.appendChild(title);

    if (data?.dataset.reportPeriod) {
        const period = document.createElement('p');
        period.className = 'rp-period';
        period.textContent = data.dataset.reportPeriod;
        root.appendChild(period);
    }

    // Table clone (full, all columns — no horizontal scroll clipping).
    const srcTable = document.querySelector('#report-table-wrap table');
    if (srcTable) {
        const heading = document.createElement('h3');
        heading.textContent = 'Performance by ' + (data?.dataset.reportDimension || 'Admin');
        root.appendChild(heading);
        root.appendChild(srcTable.cloneNode(true));
    }

    // Each chart → a PNG image. toBase64Image captures exactly what's rendered.
    // Collect decode() promises so we can wait until the images are actually
    // painted before printing — otherwise window.print() fires before the data-
    // URL images decode and the PDF shows the chart titles with blank graphics.
    const store = window.__reportCharts || {};
    const decodes = [];
    Object.keys(CHART_TITLES).forEach((id) => {
        const chart = store[id];
        if (!chart || typeof chart.toBase64Image !== 'function') return;

        const block = document.createElement('div');
        block.className = 'rp-chart';

        const h = document.createElement('h3');
        h.textContent = CHART_TITLES[id];
        block.appendChild(h);

        const img = document.createElement('img');
        img.src = chart.toBase64Image('image/png', 1);
        if (typeof img.decode === 'function') {
            decodes.push(img.decode().catch(() => {}));
        }
        block.appendChild(img);

        root.appendChild(block);
    });

    return Promise.all(decodes);
};

/**
 * Build the print document, wait for the chart images to decode, THEN open the
 * print dialog. Awaiting decode is essential: without it the dialog opens before
 * the data-URL chart images have painted, so they print blank.
 */
const printReport = async () => {
    try {
        await buildReportPrint();
    } catch (e) {
        // Even if a chart image fails to decode, still print what we have.
    }
    window.print();
};

// Clean up the print root once the print dialog closes, so it never lingers in
// the DOM (it would otherwise be hidden on screen but still present).
window.addEventListener('afterprint', removeReportPrintRoot);

window.__initReportCharts = initReportCharts;
window.__destroyReportCharts = destroyReportCharts;
window.__buildReportPrint = buildReportPrint;
window.__printReport = printReport;
window.dispatchEvent(new Event('report-charts:ready'));
