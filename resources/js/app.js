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
} from "tabulator-tables";

window.Alpine = Alpine;
window.Chart = Chart;

Tabulator.registerModule([
    SortModule,
    FormatModule,
    ResizeColumnsModule,
    PageModule,
    AjaxModule,
    FilterModule,
    EditModule,
    SelectRowModule,
]);
window.Tabulator = Tabulator;

Alpine.start(); // ← paling bawah
