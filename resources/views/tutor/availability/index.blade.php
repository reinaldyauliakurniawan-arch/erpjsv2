<x-app-layout>
<x-slot name="title">Availabilitas</x-slot>

<div class="p-lg space-y-md">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Availabilitas</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Atur slot waktu yang bisa dijadwalkan admin</p>
        </div>
        <button onclick="document.getElementById('modal-add').showModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
            <span class="material-symbols-outlined text-base">add</span>
            Tambah Slot
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="alert alert-success alert-soft">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-error alert-soft">
        <span class="material-symbols-outlined">error</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Legend --}}
    <div class="flex items-center gap-md text-xs text-on-surface-variant">
        <span class="flex items-center gap-xs"><span class="w-3 h-3 rounded-full bg-success inline-block"></span> Tersedia</span>
        <span class="flex items-center gap-xs"><span class="w-3 h-3 rounded-full bg-base-300 inline-block"></span> Tidak Tersedia</span>
        <span class="flex items-center gap-xs"><span class="w-3 h-3 rounded-full bg-warning inline-block"></span> Occupied (terkunci)</span>
    </div>

    @if($availability->isEmpty())
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg text-center py-xl text-on-surface-variant">
        <span class="material-symbols-outlined text-4xl">event_available</span>
        <p class="mt-sm text-sm font-medium text-on-surface">Belum ada slot availabilitas</p>
        <p class="mt-xs text-xs">Klik "Tambah Slot" untuk menambahkan waktu yang kamu tersedia</p>
    </div>
    @else
    @php
        $grouped  = $availability->groupBy('day');
        $dayOrder = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
        $dayLabel = ['Senin'=>'Senin','Selasa'=>'Selasa','Rabu'=>'Rabu','Kamis'=>'Kamis','Jumat'=>'Jumat','Sabtu'=>'Sabtu','Minggu'=>'Minggu'];
    @endphp
    <div class="space-y-md">
        @foreach($dayOrder as $day)
        @if($grouped->has($day))
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
            <p class="text-sm font-semibold text-on-surface mb-sm">{{ $dayLabel[$day] }}</p>
            <div class="grid gap-xs" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
                @foreach($grouped[$day]->sortBy('time_block') as $slot)
                <div class="flex items-center justify-between px-md py-sm rounded-lg border
                    {{ $slot->status === 'occupied' ? 'border-warning bg-warning/10' :
                       ($slot->status === 'available' ? 'border-success bg-success/10' : 'border-surface-border bg-surface') }}">
                    <span class="text-sm text-on-surface">{{ $slot->time_block }}</span>
                    <div class="flex items-center gap-xs">
                        @if($slot->status === 'occupied')
                            <span class="badge badge-soft badge-warning text-xs">Occupied</span>
                        @else
                            {{-- Toggle --}}
                            <form method="POST" action="{{ route('tutor.availability.update', $slot->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status"
                                    value="{{ $slot->status === 'available' ? 'not_available' : 'available' }}">
                                <button type="submit"
                                    class="badge badge-soft text-xs {{ $slot->status === 'available' ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $slot->status === 'available' ? 'Tersedia' : 'Tidak' }}
                                </button>
                            </form>
                            {{-- Hapus --}}
                            <form method="POST" action="{{ route('tutor.availability.destroy', $slot->id) }}"
                                onsubmit="return confirm('Hapus slot ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error p-0">
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @endforeach
    </div>
    @endif

</div>

{{-- Modal Tambah Slot --}}
<dialog id="modal-add" class="modal">
    <div class="modal-box" style="max-width: 28rem;">
        <h3 class="text-base font-semibold text-on-surface mb-md">Tambah Slot Availabilitas</h3>

        <form method="POST" action="{{ route('tutor.availability.store') }}">
            @csrf
            <div class="space-y-md">
                <div class="fieldset">
                    <label class="fieldset-legend">Hari <span class="text-error">*</span></label>
                    <select name="day" class="select w-full" required>
                        <option value="">— Pilih Hari —</option>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                        <option value="Minggu">Minggu</option>                    </select>
                </div>

                <div class="fieldset">
                    <label class="fieldset-legend">Time Block <span class="text-error">*</span></label>
                    <select name="time_block" class="select w-full" required>
                        <option value="">— Pilih Sesi —</option>
                        <option value="09:00-10:30">09:00 - 10:30</option>
                        <option value="10:30-12:00">10:30 - 12:00</option>
                        <option value="13:00-14:30">13:00 - 14:30</option>
                        <option value="14:30-16:00">14:30 - 16:00</option>
                        <option value="16:00-17:30">16:00 - 17:30</option>
                        <option value="18:30-20:00">18:30 - 20:00</option>
                        <option value="custom">Custom...</option>
                    </select>
                </div>

                {{-- Custom time block --}}
                <div id="custom-block" class="hidden">
                    <div class="fieldset">
                        <label class="fieldset-legend">Custom Time Block</label>
                        <input type="text" id="custom-input" class="input w-full"
                            placeholder="cth: 11:30-13:00">
                    </div>
                </div>
            </div>

            <div class="modal-action mt-lg">
                <button type="button" onclick="document.getElementById('modal-add').close()"
                    class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
                    <span class="material-symbols-outlined text-base">add</span>
                    Tambah
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const timeBlockSelect = document.querySelector('select[name="time_block"]');
    const customBlock = document.getElementById('custom-block');
    const customInput = document.getElementById('custom-input');

    timeBlockSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customBlock.classList.remove('hidden');
            customInput.required = true;
            customInput.addEventListener('input', function() {
                timeBlockSelect.value = this.value || 'custom';
            });
        } else {
            customBlock.classList.add('hidden');
            customInput.required = false;
        }
    });
});
</script>

</x-app-layout>
