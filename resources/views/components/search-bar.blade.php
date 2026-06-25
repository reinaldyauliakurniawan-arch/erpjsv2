{{--
    Global search bar — injected into the topbar for admin & CFO roles only.
    Design follows the Cool Mode rules from BRAND_PERSONALITY_GUIDE.md:
    - Flat, neutral surface (no gradient, no glassmorphism)
    - Green only as accent on focus
    - Material Symbols Outlined for icons
    - 13px body text, 11px label text
    - Subtle motion (150ms transitions)
    - Keyboard-first: '/' to focus, arrows to navigate, Enter to follow

    The Alpine component state lives on this element; the dropdown is
    absolutely positioned relative to it.
--}}
@php
    $searchPlaceholder = auth()->user()->role === 'cfo'
        ? 'Cari akun, jurnal, payroll, aset...'
        : 'Cari student, tutor, program, enrollment...';
@endphp
<div class="relative" x-data='globalSearch({
        url: @json(route('search')),
        placeholder: @json($searchPlaceholder)
    })'
    @keydown.window.slash.prevent="$refs.searchInput?.focus()"
    @keydown.escape.window="if (document.activeElement === $refs.searchInput) { $refs.searchInput.blur(); close(); }"
    @click.outside="close()">

    <div class="app-search">
        <span class="material-symbols-outlined app-search__icon" aria-hidden="true">search</span>
        <input
            x-ref="searchInput"
            type="text"
            class="app-search__input"
            :placeholder="placeholder"
            x-model="query"
            @input.debounce.300ms="search()"
            @focus="onFocus()"
            @keydown.arrow-down.prevent="moveCursor(1)"
            @keydown.arrow-up.prevent="moveCursor(-1)"
            @keydown.enter.prevent="enter()"
            role="combobox"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="search-results"
            aria-label="Global search"
            autocomplete="off"
            spellcheck="false" />
        <kbd class="app-search__kbd" x-show="!query" aria-hidden="true">/</kbd>
        <span class="app-search__spinner" x-show="loading" x-cloak aria-hidden="true">
            <span class="material-symbols-outlined app-search__spin">progress_activity</span>
        </span>
    </div>

    {{-- Results dropdown --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        id="search-results"
        role="listbox"
        class="app-search__dropdown">

        {{-- Loading state --}}
        <template x-if="loading">
            <div class="app-search__empty">
                <span class="material-symbols-outlined app-search__spin" aria-hidden="true">progress_activity</span>
                <p class="text-on-surface-variant">Mencari...</p>
            </div>
        </template>

        {{-- Empty state --}}
        <template x-if="!loading && hasSearched && total === 0">
            <div class="app-search__empty">
                <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
                <p>Tidak ada hasil untuk "<span x-text="query" class="font-semibold"></span>"</p>
            </div>
        </template>

        {{-- Min chars hint --}}
        <template x-if="!loading && !hasSearched && query.length > 0 && query.length < 2">
            <div class="app-search__empty">
                <p class="text-on-surface-variant">Ketik minimal 2 karakter...</p>
            </div>
        </template>

        {{-- Initial idle state --}}
        <template x-if="!loading && !hasSearched && query.length === 0">
            <div class="app-search__empty">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                <p class="text-on-surface-variant">Mulai mengetik untuk mencari. Tekan <kbd class="app-search__kbd-inline">/</kbd> kapan saja untuk fokus.</p>
            </div>
        </template>

        {{-- Grouped results --}}
        <template x-if="!loading && total > 0">
            <div class="app-search__groups">
                <template x-for="(items, category) in results" :key="category">
                    <div class="app-search__group">
                        <p class="app-search__group-label" x-text="category"></p>
                        <template x-for="(item, idx) in items" :key="item.id + '-' + category">
                            <a
                                :href="item.url"
                                role="option"
                                :aria-selected="cursor === flatIndex(category, idx) ? 'true' : 'false'"
                                @mouseenter="cursor = flatIndex(category, idx)"
                                @click="close()"
                                class="app-search__item"
                                :class="{ 'app-search__item--active': cursor === flatIndex(category, idx) }">
                                <div class="app-search__item-label" x-text="item.label"></div>
                                <div class="app-search__item-subtitle" x-text="item.subtitle"></div>
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
