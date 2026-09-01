import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, DoughnutController, ArcElement } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, DoughnutController, ArcElement);

Chart.defaults.color = '#8b8b93';
Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

/**
 * Renders the "active devices" trend line. Reads labels/values from the
 * canvas's own data attributes so the Blade view stays free of inline JS.
 */
function renderTrendCharts() {
    document.querySelectorAll('[data-trend-chart]').forEach((canvas) => {
        const labels = JSON.parse(canvas.dataset.labels ?? '[]');
        const values = JSON.parse(canvas.dataset.values ?? '[]');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: '#e0262c',
                    backgroundColor: 'rgba(224, 38, 44, 0.12)',
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
                    y: { grid: { color: '#232326' }, beginAtZero: true },
                },
            },
        });
    });
}

/**
 * Renders a small doughnut used for stickiness (DAU/MAU).
 */
function renderDoughnutCharts() {
    document.querySelectorAll('[data-doughnut-chart]').forEach((canvas) => {
        const value = parseFloat(canvas.dataset.value ?? '0');

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [value, Math.max(0, 100 - value)],
                    backgroundColor: ['#e0262c', '#232326'],
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
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderTrendCharts();
    renderDoughnutCharts();
});
