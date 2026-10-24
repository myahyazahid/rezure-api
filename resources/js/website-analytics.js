import { Chart } from 'chart.js';
import { themeColor, hexToRgba, SERIES_TOKENS } from './chart-theme';

/**
 * Website analytics (v4) charts and widgets. Every widget reads its data
 * from a `data-*` JSON attribute rendered by the Blade view, so views stay
 * free of inline scripts — same convention as the device dashboard in
 * app.js. Values that originate from visitors (paths, referrers, cities)
 * are only ever written to the DOM with textContent, never innerHTML.
 */

const timeseriesCharts = [];
const sparklineCharts = [];
const shareDoughnuts = [];
const lazyCharts = [];

function readJson(element, attribute) {
    try {
        return JSON.parse(element.getAttribute(attribute) ?? 'null');
    } catch (error) {
        return null;
    }
}

function formatValue(value, format) {
    if (value === null || value === undefined) {
        return '–';
    }

    if (format === 'percent') {
        return `${Number(value).toFixed(1)}%`;
    }

    if (format === 'duration') {
        const seconds = Math.round(value);

        if (seconds < 60) {
            return `${seconds}s`;
        }

        return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    }

    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

/**
 * Release dates and blog publish dates drawn as dashed vertical markers, so
 * a traffic spike can be read against what caused it.
 */
const annotationPlugin = {
    id: 'webAnnotations',
    afterDatasetsDraw(chart) {
        const annotations = chart.config.options.webAnnotations ?? [];

        if (annotations.length === 0) {
            return;
        }

        const { ctx, chartArea, scales } = chart;
        const color = themeColor('subtle');

        ctx.save();
        ctx.font = "10px 'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
        ctx.fillStyle = color;
        ctx.strokeStyle = color;
        ctx.setLineDash([3, 3]);

        // Labels that would overlap (a release and a blog post the same
        // week) are stacked one line lower instead of drawn over each other.
        const lastLabelEnd = [];

        annotations.forEach((annotation) => {
            const x = scales.x.getPixelForValue(annotation.index);

            if (x < chartArea.left || x > chartArea.right) {
                return;
            }

            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.stroke();

            const width = ctx.measureText(annotation.label).width;
            const labelX = Math.min(x + 3, chartArea.right - width);
            let line = 0;

            while (lastLabelEnd[line] !== undefined && labelX < lastLabelEnd[line] + 4) {
                line++;
            }

            lastLabelEnd[line] = labelX + width;
            ctx.fillText(annotation.label, labelX, chartArea.top + 10 + line * 12);
        });

        ctx.restore();
    },
};

function applyTimeseriesColors(chart) {
    const brand = themeColor('brand');

    chart.data.datasets[0].borderColor = brand;
    chart.data.datasets[0].backgroundColor = hexToRgba(brand, 0.12);

    if (chart.data.datasets[1]) {
        chart.data.datasets[1].borderColor = themeColor('subtle');
    }

    chart.options.scales.y.grid.color = themeColor('border');
}

/**
 * The main "metric over time" chart: one card, a row of metric buttons
 * (`data-web-metric`), the current period as a filled line and the
 * comparison period as a dashed one.
 */
function renderTimeseries() {
    document.querySelectorAll('[data-web-timeseries]').forEach((canvas) => {
        const data = readJson(canvas, 'data-web-timeseries');

        if (!data) {
            return;
        }

        const card = canvas.closest('[data-web-timeseries-card]') ?? document;
        let metric = data.default ?? Object.keys(data.series)[0];

        const datasetsFor = (key) => {
            const series = data.series[key];
            const datasets = [{
                label: data.currentLabel ?? 'This period',
                data: series.current,
                fill: true,
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 2,
            }];

            if (series.previous) {
                datasets.push({
                    label: data.previousLabel ?? 'Comparison',
                    data: series.previous,
                    fill: false,
                    tension: 0.3,
                    pointRadius: 0,
                    borderWidth: 1.5,
                    borderDash: [5, 4],
                });
            }

            return datasets;
        };

        const chart = new Chart(canvas, {
            type: 'line',
            data: { labels: data.labels, datasets: datasetsFor(metric) },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                webAnnotations: data.annotations ?? [],
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${formatValue(context.parsed.y, data.series[metric].format)}`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10, autoSkip: true } },
                    y: { grid: {}, beginAtZero: true, ticks: { callback: (value) => formatValue(value, data.series[metric].format) } },
                },
            },
            plugins: [annotationPlugin],
        });

        applyTimeseriesColors(chart);
        chart.update();
        timeseriesCharts.push(chart);

        card.querySelectorAll('[data-web-metric]').forEach((button) => {
            button.addEventListener('click', () => {
                metric = button.dataset.webMetric;
                chart.data.datasets = datasetsFor(metric);
                applyTimeseriesColors(chart);
                chart.update();

                card.querySelectorAll('[data-web-metric]').forEach((other) => {
                    other.toggleAttribute('data-active', other === button);
                });
            });
        });
    });
}

function renderSparklines() {
    document.querySelectorAll('[data-sparkline]').forEach((canvas) => {
        const values = readJson(canvas, 'data-sparkline') ?? [];

        const chart = new Chart(canvas, {
            type: 'line',
            data: { labels: values.map((_, index) => index), datasets: [{ data: values, fill: true, tension: 0.35, pointRadius: 0, borderWidth: 1.5 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
            },
        });

        applySparklineColors(chart);
        chart.update();
        sparklineCharts.push(chart);
    });
}

function applySparklineColors(chart) {
    const brand = themeColor('brand');

    chart.data.datasets[0].borderColor = brand;
    chart.data.datasets[0].backgroundColor = hexToRgba(brand, 0.1);
}

/** Categorical doughnut (device split, new vs returning, ...). */
function renderShareDoughnuts() {
    document.querySelectorAll('[data-share-doughnut]').forEach((canvas) => {
        const data = readJson(canvas, 'data-share-doughnut');

        if (!data) {
            return;
        }

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: { labels: data.labels, datasets: [{ data: data.values, borderWidth: 0 }] },
            options: {
                cutout: '68%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } } },
            },
        });

        applyDoughnutColors(chart);
        chart.update();
        shareDoughnuts.push(chart);
    });
}

function applyDoughnutColors(chart) {
    chart.data.datasets[0].backgroundColor = chart.data.labels.map((_, index) => themeColor(SERIES_TOKENS[index % SERIES_TOKENS.length]));
}

/**
 * The world map and the Sankey diagram pull in chartjs-chart-geo (plus a
 * ~100 KB world topology) and chartjs-chart-sankey — loaded only on the
 * pages that actually render one.
 */
async function renderLazyCharts() {
    const geoCanvases = document.querySelectorAll('[data-geo-chart], [data-bubble-map]');
    const sankeyCanvases = document.querySelectorAll('[data-sankey-chart]');

    if (geoCanvases.length > 0) {
        const { renderGeoCharts } = await import('./website-geo');
        lazyCharts.push(...(await renderGeoCharts(geoCanvases)));
    }

    if (sankeyCanvases.length > 0) {
        const { renderSankeyCharts } = await import('./website-sankey');
        lazyCharts.push(...renderSankeyCharts(sankeyCanvases));
    }
}

/** Filter forms (period, comparison, ...) apply as soon as a select changes. */
function initAutosubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.submit());
    });

    document.querySelectorAll('[data-period-select]').forEach((select) => {
        const form = select.form;
        const custom = form?.querySelector('[data-custom-range]');

        const sync = () => {
            if (custom) {
                custom.hidden = select.value !== 'custom';
            }
        };

        select.addEventListener('change', () => {
            sync();

            if (select.value !== 'custom') {
                form?.submit();
            }
        });

        sync();
    });
}

function cell(text, className = '') {
    const td = document.createElement('td');
    td.className = className;
    td.textContent = text;

    return td;
}

function fillRows(tbody, rows, columns) {
    if (!tbody) {
        return;
    }

    tbody.replaceChildren();

    if (rows.length === 0) {
        const tr = document.createElement('tr');
        const td = cell('Nobody right now.', 'py-3 text-xs text-subtle');
        td.colSpan = columns.length;
        tr.appendChild(td);
        tbody.appendChild(tr);

        return;
    }

    rows.forEach((row) => {
        const tr = document.createElement('tr');
        tr.className = 'border-t border-border';
        columns.forEach((column, index) => {
            tr.appendChild(cell(String(row[column] ?? ''), index === 0 ? 'max-w-0 truncate py-2 pr-3' : 'py-2 text-right tabular-nums text-muted'));
        });
        tbody.appendChild(tr);
    });
}

/**
 * Realtime page: polls the JSON snapshot every 10 s while the tab is
 * visible and redraws counts, lists and the per-minute bars in place.
 */
function initRealtime() {
    const root = document.querySelector('[data-realtime]');

    if (!root) {
        return;
    }

    const minutesCanvas = root.querySelector('[data-realtime-minutes]');
    let minutesChart = null;

    if (minutesCanvas) {
        minutesChart = new Chart(minutesCanvas, {
            type: 'bar',
            data: { labels: [], datasets: [{ data: [], borderRadius: 3, maxBarThickness: 14 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 6 } }, y: { beginAtZero: true, grid: {}, ticks: { precision: 0 } } },
            },
        });
        lazyCharts.push({ chart: minutesChart, recolor: applyMinutesColors });
    }

    function applyMinutesColors(chart) {
        chart.data.datasets[0].backgroundColor = themeColor('brand');
        chart.options.scales.y.grid.color = themeColor('border');
    }

    function render(snapshot) {
        root.querySelectorAll('[data-realtime-count]').forEach((element) => {
            element.textContent = Number(snapshot.active_visitors).toLocaleString('en-US');
        });

        const pageviews = root.querySelector('[data-realtime-pageviews]');

        if (pageviews) {
            pageviews.textContent = Number(snapshot.pageviews_30m).toLocaleString('en-US');
        }

        fillRows(root.querySelector('[data-realtime-pages]'), snapshot.pages, ['path', 'visitors']);
        fillRows(root.querySelector('[data-realtime-sources]'), snapshot.sources, ['label', 'visitors']);
        fillRows(root.querySelector('[data-realtime-countries]'), snapshot.countries, ['label', 'visitors']);
        fillRows(root.querySelector('[data-realtime-feed]'), snapshot.feed, ['path', 'where', 'source', 'ago']);

        if (minutesChart) {
            minutesChart.data.labels = snapshot.minutes.map((minute) => minute.label);
            minutesChart.data.datasets[0].data = snapshot.minutes.map((minute) => minute.pageviews);
            applyMinutesColors(minutesChart);
            minutesChart.update();
        }
    }

    async function poll() {
        if (document.visibilityState === 'hidden') {
            return;
        }

        try {
            const response = await fetch(root.dataset.realtime, { headers: { Accept: 'application/json' } });

            if (response.ok) {
                render(await response.json());
            }
        } catch (error) {
            // A missed poll just waits for the next one.
        }
    }

    const initial = readJson(root, 'data-realtime-initial');

    if (initial) {
        render(initial);
    }

    setInterval(poll, 10000);
}

/**
 * Click heatmap: the live page in an iframe, scaled to fit the card, with
 * the recorded clicks painted on a canvas laid over it at the same scale.
 */
function initClickHeatmaps() {
    document.querySelectorAll('[data-click-heatmap]').forEach((root) => {
        const data = readJson(root, 'data-click-heatmap');
        const stage = root.querySelector('[data-heatmap-stage]');
        const frame = root.querySelector('iframe');
        const canvas = root.querySelector('canvas');

        if (!data || !stage || !frame || !canvas) {
            return;
        }

        const width = data.width;
        const height = Math.max(data.height, 600);

        stage.style.width = `${width}px`;
        stage.style.height = `${height}px`;
        frame.style.width = `${width}px`;
        frame.style.height = `${height}px`;
        canvas.width = width;
        canvas.height = height;

        const fit = () => {
            const scale = Math.min(1, root.clientWidth / width);
            stage.style.transform = `scale(${scale})`;
            root.style.height = `${height * scale}px`;
        };

        fit();
        window.addEventListener('resize', fit);

        const ctx = canvas.getContext('2d');
        const brand = themeColor('brand');

        data.points.forEach(([xPct, yPx, isRage]) => {
            const x = (xPct / 100) * width;
            const gradient = ctx.createRadialGradient(x, yPx, 0, x, yPx, 22);
            gradient.addColorStop(0, hexToRgba(brand, isRage ? 0.55 : 0.35));
            gradient.addColorStop(1, hexToRgba(brand, 0));
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(x, yPx, 22, 0, Math.PI * 2);
            ctx.fill();
        });
    });
}

export function updateWebsiteChartTheme() {
    timeseriesCharts.forEach((chart) => {
        applyTimeseriesColors(chart);
        chart.update();
    });

    sparklineCharts.forEach((chart) => {
        applySparklineColors(chart);
        chart.update();
    });

    shareDoughnuts.forEach((chart) => {
        applyDoughnutColors(chart);
        chart.update();
    });

    lazyCharts.forEach(({ chart, recolor }) => {
        recolor(chart);
        chart.update();
    });
}

export function initWebsiteAnalytics() {
    renderTimeseries();
    renderSparklines();
    renderShareDoughnuts();
    renderLazyCharts();
    initAutosubmit();
    initRealtime();
    initClickHeatmaps();
}
