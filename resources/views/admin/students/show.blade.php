<x-app-layout>
    <x-slot name="title">Student Detail</x-slot>

    <div class="p-lg space-y-lg">

        {{-- Breadcrumb + back link --}}
        <div class="flex items-center gap-sm">
            <a href="{{ route('admin.students.index') }}" class="btn btn-ghost btn-sm gap-xs flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span class="hidden sm:inline">Students</span>
            </a>
            <span class="text-on-surface-variant text-body-md">/</span>
            <span class="text-body-md font-semibold text-on-surface truncate">{{ $student->user?->name ?? '—' }}</span>
        </div>

        {{-- Student info card --}}
        <div class="app-card">
            <div class="flex flex-col md:flex-row md:items-start gap-lg">
                {{-- Avatar --}}
                <div class="app-avatar app-avatar--lg flex-shrink-0">
                    {{ strtoupper(substr($student->user?->name ?? '?', 0, 1)) }}
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <h1 class="text-headline-lg font-bold text-on-surface">{{ $student->user?->name ?? '—' }}</h1>
                    <p class="text-body-md text-on-surface-variant mt-xs">{{ $student->user?->email ?? '—' }}</p>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-md mt-lg">
                        <div>
                            <p class="app-section-label">Phone</p>
                            <p class="text-body-md text-on-surface mt-xs">{{ $student->user?->phone ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="app-section-label">Education Level</p>
                            <p class="text-body-md text-on-surface mt-xs">{{ $student->education_level ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="app-section-label">Active Enrollments</p>
                            <p class="text-body-md text-on-surface mt-xs">{{ $student->enrollments->where('status', 'active')->count() }}</p>
                        </div>
                        <div>
                            <p class="app-section-label">Total Enrollments</p>
                            <p class="text-body-md text-on-surface mt-xs">{{ $student->enrollments->count() }}</p>
                        </div>
                    </div>

                    @if($student->notes)
                    <div class="mt-lg">
                        <p class="app-section-label">Notes</p>
                        <p class="text-body-md text-on-surface mt-xs">{{ $student->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Enrollments --}}
        <div>
            <h2 class="text-headline-md font-semibold text-on-surface mb-md">Enrollments</h2>

            @if($student->enrollments->isEmpty())
                <div class="app-card app-empty-state">
                    <span class="material-symbols-outlined" style="font-size:2.5rem">school</span>
                    <p class="mt-sm">Belum ada enrollment untuk student ini.</p>
                </div>
            @else
                <div class="app-table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Tutors</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($student->enrollments as $enrollment)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.enrollments.show', $enrollment->id) }}" class="font-semibold text-secondary hover:underline">
                                        {{ $enrollment->program?->name ?? '—' }}
                                    </a>
                                </td>
                                <td>
                                    @if($enrollment->tutors->isEmpty())
                                        <span class="text-on-surface-variant">—</span>
                                    @else
                                        @foreach($enrollment->tutors as $tutor)
                                            <span class="badge badge-soft mr-xs">{{ $tutor->user?->name ?? '—' }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <span class="app-status-badge @if($enrollment->status === 'active') app-status-badge--success @elseif($enrollment->status === 'expired' || $enrollment->status === 'cancelled') app-status-badge--error @elseif($enrollment->status === 'waitlist') app-status-badge--warning @else app-status-badge--neutral @endif">
                                        {{ ucfirst($enrollment->status) }}
                                    </span>
                                </td>
                                <td class="font-mono">Rp {{ number_format($enrollment->total_amount ?? 0, 0, ',', '.') }}</td>
                                <td>
                                    <span class="app-status-badge @if($enrollment->payment_status === 'paid') app-status-badge--success @elseif($enrollment->payment_status === 'partial') app-status-badge--warning @else app-status-badge--neutral @endif">
                                        {{ ucfirst($enrollment->payment_status ?? 'unpaid') }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.enrollments.show', $enrollment->id) }}" class="btn btn-ghost btn-xs" aria-label="View enrollment">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
