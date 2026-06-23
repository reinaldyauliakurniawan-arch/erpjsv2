<x-app-layout>
    <x-slot name="title">Pengaturan Warna</x-slot>

    <div class="p-lg space-y-lg max-w-2xl">

        @if(session('success'))
            <div role="alert" class="alert alert-success alert-soft">
                <span class="material-symbols-outlined">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <h3 class="text-headline-lg font-semibold text-on-surface">Pengaturan Warna</h3>

        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <form method="POST" action="{{ route('admin.settings.colors.update') }}">
                @csrf
                <div class="space-y-lg">

                    <div class="space-y-xs">
                        <label class="text-body-md font-medium text-on-surface">Warna Primary</label>
                        <p class="text-body-sm text-on-surface-variant">Button, badge, elemen utama</p>
                        <div class="flex items-center gap-sm">
                            <input type="color" name="color_primary" value="{{ $color_primary }}"
                                class="w-12 h-10 rounded cursor-pointer border border-surface-border"
                                oninput="document.getElementById('text_primary').value=this.value" />
                            <input type="text" id="text_primary" value="{{ $color_primary }}"
                                class="input input-sm w-32 font-mono"
                                oninput="document.querySelector('[name=color_primary]').value=this.value" />
                        </div>
                    </div>

                    <div class="space-y-xs">
                        <label class="text-body-md font-medium text-on-surface">Warna Secondary</label>
                        <p class="text-body-sm text-on-surface-variant">Accent, avatar, highlight</p>
                        <div class="flex items-center gap-sm">
                            <input type="color" name="color_secondary" value="{{ $color_secondary }}"
                                class="w-12 h-10 rounded cursor-pointer border border-surface-border"
                                oninput="document.getElementById('text_secondary').value=this.value" />
                            <input type="text" id="text_secondary" value="{{ $color_secondary }}"
                                class="input input-sm w-32 font-mono"
                                oninput="document.querySelector('[name=color_secondary]').value=this.value" />
                        </div>
                    </div>

                    <div class="space-y-xs">
                        <label class="text-body-md font-medium text-on-surface">Warna Sidebar</label>
                        <p class="text-body-sm text-on-surface-variant">Background sidebar & mobile drawer</p>
                        <div class="flex items-center gap-sm">
                            <input type="color" name="color_sidebar" value="{{ $color_sidebar }}"
                                class="w-12 h-10 rounded cursor-pointer border border-surface-border"
                                oninput="document.getElementById('text_sidebar').value=this.value" />
                            <input type="text" id="text_sidebar" value="{{ $color_sidebar }}"
                                class="input input-sm w-32 font-mono"
                                oninput="document.querySelector('[name=color_sidebar]').value=this.value" />
                        </div>
                    </div>

                </div>

                <div class="pt-lg">
                    <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
