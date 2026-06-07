<x-app-layout>
<x-slot name="title">Practice</x-slot>

<div class="p-lg space-y-md">
    <h1 class="text-headline-lg font-semibold text-on-surface">Tugas Practice</h1>

    @if(session('success'))
        <div class="alert alert-success alert-soft">
            <span class="material-symbols-outlined">check_circle</span>
            {{ session('success') }}
        </div>
    @endif

    @forelse($practices as $practice)
        @php
            $pivot  = $practice->pivot_data;
            $status = $pivot?->completion_status ?? 'not_started';
            $opened = $pivot?->opened_at;
        @endphp

        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
            {{-- Header kartu --}}
            <div class="p-lg flex items-center justify-between border-b border-surface-border bg-surface-container-lowest">
                <div class="flex items-center gap-md">
                    <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">edit_note</span>
                    </div>
                    <div>
                        <h3 class="font-semibold text-on-surface text-base">{{ $practice->title }}</h3>
                        <p class="text-sm text-on-surface-variant mt-xs">
                            @if($practice->estimated_duration)
                                {{ $practice->estimated_duration }} menit
                            @endif
                            @if($practice->deadline)
                                | Deadline {{ $practice->deadline->translatedFormat('d M Y') }}
                            @endif
                        </p>
                    </div>
                </div>

                @if($status === 'completed')
                    <span class="badge badge-success badge-soft font-semibold">Selesai</span>
                @elseif($opened)
                    <span class="badge badge-info badge-soft font-semibold">Dibuka</span>
                @else
                    <span class="badge badge-neutral badge-soft font-semibold">Belum Dimulai</span>
                @endif
            </div>

            {{-- Body kartu --}}
            <div class="p-lg space-y-md">
                @if($practice->description)
                    <p class="text-sm text-on-surface-variant">{{ $practice->description }}</p>
                @endif

                @if($status === 'completed')
                    {{-- Sudah selesai --}}
                    <div class="flex items-center gap-xs text-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-success" style="font-size:18px;font-variation-settings:'FILL' 1">check_circle</span>
                        Dibuka & Diselesaikan
                    </div>
                    @if($pivot?->reflection)
                        <div class="bg-surface-container-low p-md rounded-lg border border-surface-border">
                            <p class="text-sm text-on-surface-variant italic">"{{ $pivot->reflection }}"</p>
                        </div>
                    @endif
                    <div class="flex justify-end pt-xs border-t border-surface-border">
                        <button class="btn btn-sm btn-success opacity-60 cursor-not-allowed gap-xs" disabled>
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            Practice Selesai ✓
                        </button>
                    </div>

                @elseif($opened)
                    {{-- Sudah dibuka, belum submit --}}
                    <div class="flex items-center justify-between text-sm text-on-surface-variant">
                        <div class="flex items-center gap-xs">
                            <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1">check_circle</span>
                            Dibuka ✓
                        </div>
                        <span class="italic">{{ \Carbon\Carbon::parse($opened)->translatedFormat('d M Y, H:i') }}</span>
                    </div>

                    @if($practice->external_link)
                        <form action="{{ route('student.practice.open', $practice) }}" method="POST" target="_blank">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm gap-xs">
                                <span class="material-symbols-outlined text-sm">open_in_new</span>
                                Buka Link Lagi
                            </button>
                        </form>
                    @endif

                    <form action="{{ route('student.practice.submit', $practice) }}" method="POST" class="space-y-md">
                        @csrf
                        <fieldset>
                            <legend class="fieldset-legend">Apa yang kamu pelajari dari practice ini?</legend>
                            <textarea
                                name="reflection"
                                rows="4"
                                class="textarea textarea-bordered w-full"
                                placeholder="Tulis refleksimu di sini..."
                            ></textarea>
                            @error('reflection')
                                <p class="text-error text-xs mt-xs">{{ $message }}</p>
                            @enderror
                        </fieldset>
                        <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm w-full">
                            Submit Selesai
                        </button>
                    </form>

                @else
                    {{-- Belum dibuka --}}
                    @if($practice->external_link)
                        <form action="{{ route('student.practice.open', $practice) }}" method="POST" target="_blank">
                            @csrf
                            <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                                <span class="material-symbols-outlined text-sm">open_in_new</span>
                                Buka Link
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-on-surface-variant italic mb-md">Tidak ada link eksternal untuk practice ini.</p>
                        <form action="{{ route('student.practice.open', $practice) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                                <span class="material-symbols-outlined text-sm">check</span>
                                Tandai Sudah Dibuka
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg text-center text-on-surface-variant">
            <span class="material-symbols-outlined text-4xl mb-md block">inbox</span>
            Belum ada practice yang di-assign ke kamu.
        </div>
    @endforelse
</div>
</x-app-layout>
