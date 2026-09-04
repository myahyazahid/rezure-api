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
            label.classList.add('text-white');
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
                    isOutsideMonth ? 'text-subtle/50' : 'text-white',
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
        });

        panel.addEventListener('click', (event) => event.stopPropagation());

        clearButton?.addEventListener('click', () => {
            input.value = '';
            label.textContent = placeholder;
            label.classList.remove('text-white');
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
    renderTrendCharts();
    renderDoughnutCharts();
    initDatePickers();
});
