{{-- Jumlah pertemuan per kelas — untuk kelas tanpa data siswa (mis. B2B, kontrak per grup). --}}
<div class="fieldset">
    <label class="fieldset-legend text-on-surface">Jumlah Pertemuan Kelas <span class="text-on-surface-variant font-normal">(opsional)</span></label>
    <input type="number" name="total_meetings" min="1" max="1000"
        value="{{ old('total_meetings', $classSession?->total_meetings) }}"
        class="input w-full @error('total_meetings') input-error @enderror" placeholder="mis. 50">
    <p class="label text-on-surface-variant">Isi untuk kelas grup tanpa data siswa (mis. B2B). Kosongkan kalau ikut paket siswa.</p>
    @error('total_meetings')<p class="label text-error">{{ $message }}</p>@enderror
</div>

<div class="fieldset">
    <label class="fieldset-legend text-on-surface">Pertemuan Sebelum Dicatat di ERP</label>
    <input type="number" name="meetings_before_start" min="0" max="1000"
        value="{{ old('meetings_before_start', $classSession?->meetings_before_start ?? 0) }}"
        class="input w-full @error('meetings_before_start') input-error @enderror">
    <p class="label text-on-surface-variant">Pertemuan yang sudah berjalan sebelum absensinya diinput di ERP.</p>
    @error('meetings_before_start')<p class="label text-error">{{ $message }}</p>@enderror
</div>
