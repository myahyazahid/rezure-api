import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, DoughnutController, ArcElement, BarController, BarElement } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, DoughnutController, ArcElement, BarController, BarElement);

Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

/**
 * Chart colours come from the same CSS custom properties the Tailwind theme
 * tokens resolve to, so light/dark stays defined in one place (app.css).
 */
function themeColor(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim();
}

function hexToRgba(hex, alpha) {
    const value = parseInt(hex.replace('#', ''), 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const trendCharts = [];
const doughnutCharts = [];
const barCharts = [];
const stackedBarCharts = [];
const multilineCharts = [];

/** Fixed order — see app.css: never reassign or cycle these per chart. */
const SERIES_TOKENS = ['series-1', 'series-2', 'series-3', 'series-4', 'series-5'];

function applyTrendColors(chart) {
    const brand = themeColor('brand');

    chart.data.datasets[0].borderColor = brand;
    chart.data.datasets[0].backgroundColor = hexToRgba(brand, 0.12);
    chart.options.scales.y.grid.color = themeColor('border');
}

function applyDoughnutColors(chart) {
    chart.data.datasets[0].backgroundColor = [themeColor('brand'), themeColor('border')];
}

/** Single-series bar chart (day-of-week, session-length histogram). */
function applyBarColors(chart) {
    chart.data.datasets[0].backgroundColor = themeColor('brand');
    chart.options.scales.y.grid.color = themeColor('border');
}

/** Two-series stacked bar (new vs returning devices). */
function applyStackedBarColors(chart) {
    chart.data.datasets[0].backgroundColor = themeColor('brand');
    chart.data.datasets[1].backgroundColor = themeColor('series-1');
    chart.options.scales.y.grid.color = themeColor('border');
}

/** Multi-line chart (growth per region) — one fixed categorical hue per series. */
function applyMultilineColors(chart) {
    chart.data.datasets.forEach((dataset, index) => {
        const color = themeColor(SERIES_TOKENS[index % SERIES_TOKENS.length]);
        dataset.borderColor = color;
        dataset.backgroundColor = color;
        dataset.pointBackgroundColor = color;
    });
    chart.options.scales.y.grid.color = themeColor('border');
}

/** Re-colours every rendered chart after the theme is toggled. */
function updateChartTheme() {
    Chart.defaults.color = themeColor('muted');

    trendCharts.forEach((chart) => {
        applyTrendColors(chart);
        chart.update();
    });

    doughnutCharts.forEach((chart) => {
        applyDoughnutColors(chart);
        chart.update();
    });

    barCharts.forEach((chart) => {
        applyBarColors(chart);
        chart.update();
    });

    stackedBarCharts.forEach((chart) => {
        applyStackedBarColors(chart);
        chart.update();
    });

    multilineCharts.forEach((chart) => {
        applyMultilineColors(chart);
        chart.update();
    });
}

function initThemeToggle() {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const isDark = document.documentElement.classList.toggle('dark');

            try {
                localStorage.setItem('rezure-theme', isDark ? 'dark' : 'light');
            } catch (error) {
                // Storage blocked (private mode) — the toggle still works for this page.
            }

            updateChartTheme();
        });
    });
}

/**
 * Renders the "active devices" trend line. Reads labels/values from the
 * canvas's own data attributes so the Blade view stays free of inline JS.
 */
function renderTrendCharts() {
    document.querySelectorAll('[data-trend-chart]').forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels ?? '[]');
        const values = JSON.parse(canvas.dataset.values ?? '[]');

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: { grid: {}, beginAtZero: true },
                },
            },
        });

        applyTrendColors(chart);
        chart.update();
        trendCharts.push(chart);
    });
}

/**
 * Renders a small doughnut used for stickiness (DAU/MAU).
 */
function renderDoughnutCharts() {
    document.querySelectorAll('[data-doughnut-chart]').forEach((canvas) => {
        const value = parseFloat(canvas.dataset.value ?? '0');

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [value, Math.max(0, 100 - value)],
                    borderWidth: 0,
                }],
            },
            options: {
                cutout: '75%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
            },
        });

        applyDoughnutColors(chart);
        chart.update();
        doughnutCharts.push(chart);
    });
}

/**
 * Single-series bar chart — day-of-week traffic, session-length histogram.
 * Bars are capped at 24px thick with a 4px rounded end (never the baseline)
 * and gap between bars, per the dashboard's mark spec.
 */
function renderBarCharts() {
    document.querySelectorAll('[data-bar-chart]').forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels ?? '[]');
        const values = JSON.parse(canvas.dataset.values ?? '[]');

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderRadius: 4,
                    borderSkipped: 'bottom',
                    maxBarThickness: 24,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: {}, beginAtZero: true },
                },
            },
        });

        applyBarColors(chart);
        chart.update();
        barCharts.push(chart);
    });
}

/** Two-series stacked bar — new vs returning devices per day. */
function renderStackedBarCharts() {
    document.querySelectorAll('[data-stacked-bar-chart]').forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels ?? '[]');
        const newValues = JSON.parse(canvas.dataset.newValues ?? '[]');
        const returningValues = JSON.parse(canvas.dataset.returningValues ?? '[]');

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'New', data: newValues, borderRadius: 4, maxBarThickness: 24 },
                    { label: 'Returning', data: returningValues, borderRadius: 4, maxBarThickness: 24 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: { stacked: true, grid: {}, beginAtZero: true },
                },
            },
        });

        applyStackedBarColors(chart);
        chart.update();
        stackedBarCharts.push(chart);
    });
}

/** Multi-line chart — per-country growth trend, one fixed hue per series. */
function renderMultilineCharts() {
    document.querySelectorAll('[data-multiline-chart]').forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels ?? '[]');
        const series = JSON.parse(canvas.dataset.series ?? '[]');

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: series.map((entry) => ({
                    label: entry.name,
                    data: entry.data,
                    tension: 0.35,
                    pointRadius: 0,
                    borderWidth: 2,
                    fill: false,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: series.length > 1 ? { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } } : { display: false },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: { grid: {}, beginAtZero: true },
                },
            },
        });

        applyMultilineColors(chart);
        chart.update();
        multilineCharts.push(chart);
    });
}

const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

function toIsoDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function toDisplayDate(date) {
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

/**
 * Wires up `<x-dashboard.date-picker>` — a from-scratch calendar dropdown
 * (no date-picker dependency in package.json) matching the dashboard's dark
 * theme, since the native `<input type=date>` picker can't be restyled.
 * Picking a day submits the enclosing filter form immediately, same as the
 * status/category `<select>`s next to it.
 */
function initDatePickers() {
    const roots = document.querySelectorAll('[data-date-picker]');
    if (roots.length === 0) {
        return;
    }

    roots.forEach((root) => {
        const input = root.querySelector('[data-date-picker-input]');
        const toggle = root.querySelector('[data-date-picker-toggle]');
        const label = root.querySelector('[data-date-picker-label]');
        const panel = root.querySelector('[data-date-picker-panel]');
        const monthSelect = root.querySelector('[data-date-picker-month]');
        const yearSelect = root.querySelector('[data-date-picker-year]');
        const daysGrid = root.querySelector('[data-date-picker-days]');
        const clearButton = root.querySelector('[data-date-picker-clear]');
        const todayButton = root.querySelector('[data-date-picker-today]');
        const placeholder = label.dataset.placeholder ?? label.textContent;

        const initial = input.value ? new Date(`${input.value}T00:00:00`) : new Date();
        let viewMonth = initial.getMonth();
        let viewYear = initial.getFullYear();

        MONTH_NAMES.forEach((name, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = name;
            monthSelect.appendChild(option);
        });

        const thisYear = new Date().getFullYear();
        for (let year = thisYear - 4; year <= thisYear + 1; year++) {
            const option = document.createElement('option');
            option.value = String(year);
            option.textContent = String(year);
            yearSelect.appendChild(option);
        }

        function selectDay(date) {
            input.value = toIsoDate(date);
            label.textContent = toDisplayDate(date);
            label.classList.remove('text-subtle');
            label.classList.add('text-foreground');
            panel.hidden = true;
            input.closest('form')?.submit();
        }

        function renderDays() {
            monthSelect.value = String(viewMonth);
            yearSelect.value = String(viewYear);
            daysGrid.innerHTML = '';

            const firstOfMonth = new Date(viewYear, viewMonth, 1);
            const startOffset = firstOfMonth.getDay();
            const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();
            const selectedIso = input.value;
            const todayIso = toIsoDate(new Date());
            const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

            for (let cell = 0; cell < totalCells; cell++) {
                const dayNumber = cell - startOffset + 1;
                let cellDate;
                let isOutsideMonth;

                if (dayNumber < 1) {
                    cellDate = new Date(viewYear, viewMonth - 1, daysInPrevMonth + dayNumber);
                    isOutsideMonth = true;
                } else if (dayNumber > daysInMonth) {
                    cellDate = new Date(viewYear, viewMonth + 1, dayNumber - daysInMonth);
                    isOutsideMonth = true;
                } else {
                    cellDate = new Date(viewYear, viewMonth, dayNumber);
                    isOutsideMonth = false;
                }

                const iso = toIsoDate(cellDate);
                const isSelected = iso === selectedIso;
                const isToday = iso === todayIso;

                const day = document.createElement('button');
                day.type = 'button';
                day.textContent = String(cellDate.getDate());
                day.className = [
                    'flex h-7 w-7 items-center justify-center rounded-md transition-colors',
                    isOutsideMonth ? 'text-subtle/50' : 'text-foreground',
                    isSelected ? 'bg-brand text-white' : 'hover:bg-surface',
                    !isSelected && isToday ? 'ring-1 ring-inset ring-brand/60' : '',
                ].filter(Boolean).join(' ');
                day.addEventListener('click', () => selectDay(cellDate));

                daysGrid.appendChild(day);
            }
        }

        renderDays();

        monthSelect.addEventListener('change', () => {
            viewMonth = Number(monthSelect.value);
            renderDays();
        });

        yearSelect.addEventListener('change', () => {
            viewYear = Number(yearSelect.value);
            renderDays();
        });

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = panel.hidden;
            document.querySelectorAll('[data-date-picker-panel]').forEach((p) => { p.hidden = true; });
            panel.hidden = !willOpen;

            if (willOpen) {
                // Right-align the panel instead of left when it would
                // otherwise spill past the right edge of the viewport (e.g.
                // the last filter in a row).
                const overflowsRight = toggle.getBoundingClientRect().left + panel.offsetWidth > window.innerWidth - 16;
                panel.classList.toggle('right-0', overflowsRight);
                panel.classList.toggle('left-0', !overflowsRight);
            }
        });

        panel.addEventListener('click', (event) => event.stopPropagation());

        clearButton?.addEventListener('click', () => {
            input.value = '';
            label.textContent = placeholder;
            label.classList.remove('text-foreground');
            label.classList.add('text-subtle');
            panel.hidden = true;
            input.closest('form')?.submit();
        });

        todayButton?.addEventListener('click', () => {
            const now = new Date();
            viewMonth = now.getMonth();
            viewYear = now.getFullYear();
            renderDays();
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-date-picker-panel]').forEach((p) => { p.hidden = true; });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.color = themeColor('muted');

    renderTrendCharts();
    renderDoughnutCharts();
    renderBarCharts();
    renderStackedBarCharts();
    renderMultilineCharts();
    initDatePickers();
    initThemeToggle();
});
