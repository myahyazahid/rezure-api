import { Chart } from 'chart.js';
import { BubbleMapController, ChoroplethController, ColorScale, GeoFeature, ProjectionScale, SizeScale, topojson } from 'chartjs-chart-geo';
import world from 'world-atlas/countries-110m.json';
import { themeColor, hexToRgba } from './chart-theme';

Chart.register(ChoroplethController, BubbleMapController, GeoFeature, ColorScale, ProjectionScale, SizeScale);

/**
 * world-atlas shapes are keyed by ISO 3166-1 numeric code ("360" for
 * Indonesia) — the Blade view passes values keyed the same way.
 */
const countries = topojson.feature(world, world.objects.countries).features;

function mix(fromHex, toHex, amount) {
    const parse = (hex) => {
        const value = parseInt(hex.replace('#', ''), 16);

        return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
    };

    const from = parse(fromHex);
    const to = parse(toHex);
    const channel = (index) => Math.round(from[index] + (to[index] - from[index]) * amount);

    return `rgb(${channel(0)}, ${channel(1)}, ${channel(2)})`;
}

function applyChoroplethColors(chart) {
    const empty = themeColor('surface-raised');
    const brand = themeColor('brand');

    chart.options.scales.color.interpolate = (value) => mix(empty, brand, 0.15 + value * 0.85);
    chart.data.datasets[0].borderColor = themeColor('border');
}

function applyBubbleColors(chart) {
    chart.data.datasets[0].backgroundColor = hexToRgba(themeColor('brand'), 0.55);
    chart.data.datasets[0].borderColor = themeColor('brand');
    chart.data.datasets[0].outlineBorderColor = themeColor('border');
    chart.data.datasets[0].outlineBackgroundColor = themeColor('surface-raised');
}

function renderChoropleth(canvas) {
    const data = JSON.parse(canvas.getAttribute('data-geo-chart') ?? '{}');
    const values = data.values ?? {};

    const chart = new Chart(canvas, {
        type: 'choropleth',
        data: {
            labels: countries.map((country) => country.properties.name),
            datasets: [{
                label: data.metric ?? 'Visitors',
                outline: countries,
                data: countries.map((country) => ({ feature: country, value: values[country.id] ?? 0 })),
                borderWidth: 0.5,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            showOutline: true,
            showGraticule: false,
            plugins: { legend: { display: false } },
            scales: {
                projection: { axis: 'x', projection: 'equalEarth' },
                color: { axis: 'x', quantize: 5, legend: { position: 'bottom-left', align: 'bottom' } },
            },
        },
    });

    applyChoroplethColors(chart);
    chart.update();

    return { chart, recolor: applyChoroplethColors };
}

function renderBubbleMap(canvas) {
    const data = JSON.parse(canvas.getAttribute('data-bubble-map') ?? '{}');
    const points = data.points ?? [];

    const chart = new Chart(canvas, {
        type: 'bubbleMap',
        data: {
            labels: points.map((point) => point.label),
            datasets: [{
                label: data.metric ?? 'Visitors',
                outline: countries,
                showOutline: true,
                data: points.map((point) => ({ longitude: point.lon, latitude: point.lat, value: point.value })),
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                projection: { axis: 'x', projection: 'equalEarth' },
                size: { axis: 'x', size: [3, 22], display: false },
            },
        },
    });

    applyBubbleColors(chart);
    chart.update();

    return { chart, recolor: applyBubbleColors };
}

export async function renderGeoCharts(canvases) {
    return Array.from(canvases).map((canvas) => (canvas.hasAttribute('data-geo-chart') ? renderChoropleth(canvas) : renderBubbleMap(canvas)));
}
