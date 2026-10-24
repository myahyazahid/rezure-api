import { Chart } from 'chart.js';
import { Flow, SankeyController } from 'chartjs-chart-sankey';
import { themeColor, hexToRgba, SERIES_TOKENS } from './chart-theme';

Chart.register(SankeyController, Flow);

/**
 * Node names arrive prefixed with their step ("1 · /", "2 · /download"), so
 * the same page at two depths is two nodes — the step decides the colour.
 */
function stepColor(node) {
    const step = parseInt(String(node).split(' ')[0], 10) || 1;

    return themeColor(SERIES_TOKENS[(step - 1) % SERIES_TOKENS.length]);
}

function applySankeyColors(chart) {
    const dataset = chart.data.datasets[0];

    dataset.colorFrom = (context) => hexToRgba(stepColor(context.dataset.data[context.dataIndex].from), 0.8);
    dataset.colorTo = (context) => hexToRgba(stepColor(context.dataset.data[context.dataIndex].to), 0.8);
    dataset.color = themeColor('foreground');
    dataset.borderColor = themeColor('border');
}

export function renderSankeyCharts(canvases) {
    return Array.from(canvases).map((canvas) => {
        const data = JSON.parse(canvas.getAttribute('data-sankey-chart') ?? '{}');

        const chart = new Chart(canvas, {
            type: 'sankey',
            data: {
                datasets: [{
                    data: data.flows ?? [],
                    colorMode: 'gradient',
                    size: 'max',
                    nodePadding: 14,
                    font: { size: 11 },
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
            },
        });

        applySankeyColors(chart);
        chart.update();

        return { chart, recolor: applySankeyColors };
    });
}
