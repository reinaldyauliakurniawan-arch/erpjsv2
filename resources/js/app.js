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

// ── Global search Alpine component ──────────────────────────────────
// Registered on window so the x-data="globalSearch({...})" call in
// components/search-bar.blade.php resolves. Loaded BEFORE Alpine.start()
// so the component is available when Alpine walks the DOM.
//
// Design rationale:
//   - Debounced 300ms on input to avoid hammering the server.
//   - AbortController cancels in-flight requests when the user types more
//     (prevents race conditions where an old slow response overwrites a new one).
//   - Cursor navigation with arrow keys + Enter to follow the highlighted result.
//   - Mouse hover also moves the cursor (so click + keyboard feel unified).
//   - flatIndex() maps (category, idx) to a flat 0..N-1 index so cursor can
//     traverse the whole flattened result list with up/down arrows.
window.globalSearch = function (config) {
    return {
        url: config.url,
        placeholder: config.placeholder,

        query: '',
        results: {},
        loading: false,
        open: false,
        hasSearched: false,
        cursor: -1,
        _abortController: null,

        get total() {
            return Object.values(this.results).reduce((sum, items) => sum + items.length, 0);
        },

        onFocus() {
            // Only auto-open if we already have results from a previous search.
            if (this.total > 0 || this.hasSearched) {
                this.open = true;
            }
        },

        async search() {
            const q = this.query.trim();

            // Below minimum length — clear results, close dropdown.
            if (q.length < 2) {
                this.results = {};
                this.hasSearched = false;
                this.cursor = -1;
                this.open = q.length > 0; // show "min 2 chars" hint
                return;
            }

            // Cancel any in-flight request before firing a new one.
            if (this._abortController) {
                this._abortController.abort();
            }
            this._abortController = new AbortController();

            this.loading = true;
            this.open = true;

            try {
                const url = new URL(this.url, window.location.origin);
                url.searchParams.set('q', q);

                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: this._abortController.signal,
                });

                if (!res.ok) {
                    throw new Error('Search failed: ' + res.status);
                }

                const data = await res.json();
                this.results = data.results || {};
                this.hasSearched = true;
                this.cursor = this.total > 0 ? 0 : -1;
            } catch (e) {
                // AbortError is expected when the user types more — silently ignore.
                if (e.name !== 'AbortError') {
                    console.error('Global search failed:', e);
                    this.results = {};
                    this.hasSearched = true;
                    this.cursor = -1;
                }
            } finally {
                this.loading = false;
            }
        },

        /**
         * Map (category, idx) to a flat index for arrow-key navigation.
         */
        flatIndex(category, idx) {
            let flat = 0;
            for (const [cat, items] of Object.entries(this.results)) {
                if (cat === category) return flat + idx;
                flat += items.length;
            }
            return flat + idx;
        },

        /**
         * Inverse of flatIndex — given a flat cursor, return {item} or null.
         */
        itemAt(flat) {
            let acc = 0;
            for (const [cat, items] of Object.entries(this.results)) {
                if (flat < acc + items.length) {
                    const idx = flat - acc;
                    return { category: cat, idx, item: items[idx] };
                }
                acc += items.length;
            }
            return null;
        },

        moveCursor(delta) {
            if (this.total === 0) return;
            this.cursor = (this.cursor + delta + this.total) % this.total;
        },

        enter() {
            if (this.cursor < 0) return;
            const hit = this.itemAt(this.cursor);
            if (hit && hit.item.url) {
                window.location.href = hit.item.url;
            }
        },

        close() {
            this.open = false;
        },
    };
};

// ── App Shell Alpine component ──────────────────────────────────────
// Controls sidebar visibility across desktop (collapse) and mobile (drawer).
// Registered before Alpine.start() so it's available when the DOM is walked.
window.appShell = function () {
    return {
        collapsed: false,   // desktop: sidebar collapsed?
        mobileOpen: false,  // mobile: drawer open?

        toggle() {
            if (window.innerWidth >= 1024) {
                this.collapsed = !this.collapsed;
            } else {
                this.mobileOpen = !this.mobileOpen;
            }
        },

        init() {
            // Close mobile drawer when resizing to desktop
            const mq = window.matchMedia('(min-width: 1024px)');
            mq.addEventListener('change', (e) => {
                if (e.matches) {
                    this.mobileOpen = false;
                }
            });
        },
    };
};

Alpine.start(); // ← paling bawah
