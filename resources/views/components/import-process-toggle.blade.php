@props(['triggers'])

{{-- Pilihan: masukkan data saja, atau sekalian jalankan proses aplikasi. --}}
<label class="flex items-start gap-sm text-body-sm cursor-pointer bg-surface-container-low rounded-lg p-sm border border-surface-border">
    <input type="checkbox" name="run_processes" value="1" checked class="checkbox checkbox-sm mt-0.5" />
    <span>
        <span class="font-medium text-on-surface">Setelah data masuk, jalankan juga proses aplikasi</span>
        <span class="block text-on-surface-variant mt-0.5">{{ $triggers }}</span>
        <span class="block text-on-surface-variant mt-0.5 italic">
            Hilangkan centang kalau hanya ingin memasukkan data mentah tanpa efek apa pun
            (mis. sedang memindahkan data lama dan jurnalnya diimpor terpisah).
        </span>
    </span>
</label>
