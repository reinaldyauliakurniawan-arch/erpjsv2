import "./bootstrap";
import Alpine from "alpinejs";
import Chart from "chart.js/auto";
import {
    Tabulator,
    SortModule,
    FormatModule,
    ResizeColumnsModule,
    PageModule,
    AjaxModule,
    FilterModule,
    EditModule,
    SelectRowModule,
    ResponsiveLayoutModule,
} from "tabulator-tables";

window.Alpine = Alpine;
window.Chart = Chart;

// ── Chart.js global defaults ─────────────────────────────────────────
// Per Brand Guide Section 4: Montserrat is the only text font in the app.
// Without this override, Chart.js falls back to 'Helvetica Neue' which
// breaks visual consistency with the rest of the UI. Setting this here
// (centralized, once) means every `new Chart(...)` instance inherits
// Montserrat — no need to repeat it per-chart in the Blade views.
Chart.defaults.font.family =
    '"Montserrat", ui-sans-serif, system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = "#4b5563"; // matches --color-on-surface-variant (AA on #f3f4f6)

// ── Tabulator global modules ─────────────────────────────────────────
Tabulator.registerModule([
    SortModule,
    FormatModule,
    ResizeColumnsModule,
    PageModule,
    AjaxModule,
    FilterModule,
    EditModule,
    SelectRowModule,
    ResponsiveLayoutModule,
]);
window.Tabulator = Tabulator;

Alpine.start(); // ← paling bawah
