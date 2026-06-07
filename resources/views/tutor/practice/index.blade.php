<x-app-layout>
<x-slot name="title">Tugas Saya</x-slot>
<div class="p-lg space-y-md">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-on-surface">Tugas Practice</h1>
        <a href="{{ route('tutor.practice.create') }}" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">
            <span class="material-symbols-outlined text-base">add</span> Buat Tugas
        </a>
    </div>
    @if(session('success'))
        <div class="alert alert-success alert-soft"><span class="material-symbols-outlined">check_circle</span> {{ session('success') }}</div>
    @endif
    @forelse($practices as $practice)
    <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex items-center justify-between">
        <div>
            <p class="font-semibold text-on-surface">{{ $practice->title }}</p>
            <p class="text-xs text-on-surface-variant mt-xs">
                {{ $practice->deadline ? 'Deadline: '.$practice->deadline->format('d M Y') : 'Tanpa deadline' }}
            </p>
        </div>
        <span class="badge badge-soft {{ $practice->status === 'published' ? 'badge-success' : 'badge-ghost' }} text-xs">
            {{ ucfirst($practice->status) }}
        </span>
    </div>
    @empty
    <div class="text-center text-on-surface-variant py-xl">
        <span class="material-symbols-outlined text-4xl block mb-sm">inbox</span>
        Belum ada tugas. Klik "Buat Tugas" untuk mulai.
    </div>
    @endforelse
</div>
</x-app-layout>
