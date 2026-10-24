/**
 * Chart colours come from the same CSS custom properties the Tailwind theme
 * tokens resolve to, so light/dark stays defined in one place (app.css).
 * Shared by the device dashboard (app.js) and the website analytics charts
 * (website-analytics.js).
 */
export function themeColor(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim();
}

export function hexToRgba(hex, alpha) {
    const value = parseInt(hex.replace('#', ''), 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/** Fixed order — see app.css: never reassign or cycle these per chart. */
export const SERIES_TOKENS = ['series-1', 'series-2', 'series-3', 'series-4', 'series-5'];
