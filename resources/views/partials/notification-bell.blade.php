{{-- Lonceng notifikasi in-app — tampil di topbar untuk SEMUA peran. --}}
<div
    x-data="{
        open: false,
        loading: false,
        unread: 0,
        items: [],
        async load() {
            this.loading = true;
            try {
                const r = await fetch(@js(route('notifications.index')), { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.unread = d.unread;
                this.items = d.items;
            } catch (e) { /* diam */ }
            this.loading = false;
        },
        async openPanel() {
            this.open = !this.open;
            if (this.open) await this.load();
        },
        async click(item) {
            if (!item.read) {
                fetch(@js(url('/notifications')) + '/' + item.id + '/read', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
            }
            if (item.url) window.location.href = item.url;
        },
        async markAll() {
            await fetch(@js(route('notifications.read-all')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            this.unread = 0;
            this.items = this.items.map(i => ({ ...i, read: true }));
        },
    }"
    x-init="load(); setInterval(() => { if (!open) load(); }, 60000)"
    @click.outside="open = false"
    class="relative flex-shrink-0"
>
    <button type="button" @click="openPanel()" aria-label="Notifikasi"
        class="relative inline-flex items-center justify-center w-9 h-9 rounded-full hover:bg-surface-container-high transition-colors">
        <span class="material-symbols-outlined text-on-surface-variant">notifications</span>
        <span x-show="unread > 0" x-cloak
            class="absolute -top-0.5 -right-0.5 min-w-[16px] h-[16px] px-[3px] rounded-full bg-error text-white text-[10px] font-bold leading-[16px] text-center"
            x-text="unread > 9 ? '9+' : unread"></span>
    </button>

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-surface-container border border-outline-variant rounded-xl shadow-lg z-50 overflow-hidden">

        <div class="flex items-center justify-between px-md py-sm border-b border-outline-variant">
            <p class="text-body-md font-semibold text-on-surface">Notifikasi</p>
            <button type="button" @click="markAll()" x-show="unread > 0"
                class="text-label-lg text-primary hover:underline">Tandai semua dibaca</button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto">
            <template x-if="loading">
                <div class="px-md py-lg text-center text-on-surface-variant text-body-sm">Memuat...</div>
            </template>
            <template x-if="!loading && items.length === 0">
                <div class="px-md py-lg text-center text-on-surface-variant text-body-sm">Belum ada notifikasi.</div>
            </template>
            <template x-for="item in items" :key="item.id">
                <button type="button" @click="click(item)"
                    class="w-full text-left flex gap-sm px-md py-sm hover:bg-surface-container-high transition-colors border-b border-outline-variant/50"
                    :class="{ 'bg-primary/5': !item.read }">
                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant flex-shrink-0 mt-0.5" x-text="item.icon"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-body-sm font-semibold text-on-surface" x-text="item.title"></span>
                        <span class="block text-body-sm text-on-surface-variant" x-text="item.body"></span>
                        <span class="block text-label-lg text-on-surface-variant/70 mt-0.5" x-text="item.ago"></span>
                    </span>
                    <span x-show="!item.read" class="w-2 h-2 rounded-full bg-primary flex-shrink-0 mt-1.5"></span>
                </button>
            </template>
        </div>
    </div>
</div>
