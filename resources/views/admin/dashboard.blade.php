<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <div class="p-lg space-y-lg" x-data="{ activeWaiting: 'reguler', privateTab: 'private' }">

        {{-- Welcome --}}
        <div>
            <h1 class="text-headline-lg font-bold text-on-surface">Dashboard Utama</h1>
            <p class="text-body-md text-on-surface-variant mt-xs">Ringkasan operasional Just Speak hari ini.</p>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-md">

            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md">
                <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-secondary">school</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Jumlah Siswa</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $stats['students_count'] }}</p>
                </div>
            </div>

            <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md">
                <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-secondary">person_search</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Total Tutors</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $stats['tutors_count'] }}</p>
                </div>
            </div>

            <button @click="activeWaiting = 'reguler'"
                :class="activeWaiting === 'reguler' ? 'ring-2 ring-secondary' : ''"
                class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg flex flex-col gap-md text-left transition-all hover:bg-surface-container-low">
                <div class="flex items-center justify-between w-full">
                    <div class="w-10 h-10 rounded-lg bg-warning/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-warning">groups</span>
                    </div>
                    <span class="badge badge-soft text-xs">Reguler</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Waiting List Reguler</p>
                    <p class="text-headline-lg font-bold text-on-surface mt-xs">{{ $waitingReguler->count() }}</p>
                </div>
            </button>

            <button @click="activeWaiting = 'private'"
                :class="activeWaiting === 'private' ? 'ring-2 ring-error' : ''"
                class="bg-error-container border border-surface-border rounded-lg p-lg flex flex-col gap-md text-left transition-all hover:opacity-90">
                <div class="flex items-center justify-between w-full">
                    <div class="w-10 h-10 rounded-lg bg-error/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-error">person_search</span>
                    </div>
                    <span class="badge badge-error badge-soft text-xs">Priority</span>
                </div>
                <div>
                    <p class="text-label-lg text-on-surface-variant uppercase tracking-widest">Waiting List Private</p>
                    <p class="text-headline-lg font-bold text-error mt-xs">{{ $waitingPrivate->count() + $waitingSemi->count() }}</p>
                </div>
            </button>

        </div>

        {{-- Waiting List Table --}}
        <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
            <div class="px-lg py-md border-b border-surface-border flex items-center justify-between">
                <h2 class="text-headline-md font-semibold text-on-surface"
                    x-text="activeWaiting === 'reguler' ? 'Daftar Tunggu Reguler' : 'Daftar Tunggu Private / Semi-Private'">
                </h2>
                <div x-show="activeWaiting === 'private'" class="flex gap-xs">
                    <button @click="privateTab = 'private'"
                        :class="privateTab === 'private' ? 'btn-primary' : 'btn-ghost'"
                        class="btn btn-xs">Private</button>
                    <button @click="privateTab = 'semi'"
                        :class="privateTab === 'semi' ? 'btn-primary' : 'btn-ghost'"
                        class="btn btn-xs">Semi-Private</button>
                </div>
            </div>

            {{-- Reguler --}}
            <div x-show="activeWaiting === 'reguler'" class="overflow-x-auto">
                @if($waitingReguler->isEmpty())
                    <p class="p-lg text-body-md text-on-surface-variant">Tidak ada siswa di waiting list reguler.</p>
                @else
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-label-lg text-on-surface-variant">
                                <th>Nama Siswa</th>
                                <th>Program</th>
                                <th>Tanggal Daftar</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitingReguler as $e)
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-secondary text-xs">
                                            {{ strtoupper(substr($e->student->user->name, 0, 2)) }}
                                        </div>
                                        <span class="font-semibold text-body-md">{{ $e->student->user->name }}</span>
                                    </div>
                                </td>
                                <td class="text-body-md">{{ $e->program->name }}</td>
                                <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                <td>
                                    @if($e->class_session_id)
                                        <span class="badge badge-success badge-soft">Ada Kelas</span>
                                    @else
                                        <span class="badge badge-warning badge-soft">Belum Ada Kelas</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Private --}}
            <div x-show="activeWaiting === 'private'">
                <div x-show="privateTab === 'private'" class="overflow-x-auto">
                    @if($waitingPrivate->isEmpty())
                        <p class="p-lg text-body-md text-on-surface-variant">Tidak ada siswa di waiting list private.</p>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr class="text-label-lg text-on-surface-variant">
                                    <th>Nama Siswa</th>
                                    <th>Program</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Status Penempatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($waitingPrivate as $e)
                                <tr class="hover:bg-surface-container-low transition-colors">
                                    <td>
                                        <div class="flex items-center gap-sm">
                                            <div class="w-8 h-8 rounded-full bg-error/20 flex items-center justify-center font-bold text-error text-xs">
                                                {{ strtoupper(substr($e->student->user->name, 0, 2)) }}
                                            </div>
                                            <span class="font-semibold text-body-md">{{ $e->student->user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-body-md">{{ $e->program->name }}</td>
                                    <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                    <td>
                                        @if($e->tutors->isEmpty())
                                            <span class="text-on-surface-variant text-body-md italic">Belum dapat tutor</span>
                                        @else
                                            <span class="badge badge-success badge-soft">{{ $e->tutors->first()->user->name ?? '-' }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div x-show="privateTab === 'semi'" class="overflow-x-auto">
                    @if($waitingSemi->isEmpty())
                        <p class="p-lg text-body-md text-on-surface-variant">Tidak ada siswa di waiting list semi-private.</p>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr class="text-label-lg text-on-surface-variant">
                                    <th>Nama Siswa</th>
                                    <th>Program</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($waitingSemi as $e)
                                <tr class="hover:bg-surface-container-low transition-colors">
                                    <td>
                                        <div class="flex items-center gap-sm">
                                            <div class="w-8 h-8 rounded-full bg-warning/20 flex items-center justify-center font-bold text-warning text-xs">
                                                {{ strtoupper(substr($e->student->user->name, 0, 2)) }}
                                            </div>
                                            <span class="font-semibold text-body-md">{{ $e->student->user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-body-md">{{ $e->program->name }}</td>
                                    <td class="text-body-md">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}</td>
                                    <td>
                                        @if($e->class_session_id)
                                            <span class="badge badge-success badge-soft">Sudah ada kelas</span>
                                        @else
                                            <span class="badge badge-warning badge-soft">Belum ada kelas</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bottom Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">

            {{-- Left --}}
            <div class="lg:col-span-8 space-y-lg">

                {{-- Expiring Enrollments --}}
                <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                    <div class="px-lg py-md border-b border-surface-border flex items-center gap-sm">
                        <span class="material-symbols-outlined text-warning">schedule</span>
                        <h3 class="text-headline-md font-semibold text-on-surface flex-1">Enrollments Expiring in 7 Days</h3>
                        <span class="badge badge-soft">{{ $expiringEnrollments->count() }} total</span>
                    </div>
                    @if($expiringEnrollments->isEmpty())
                        <p class="p-lg text-body-md text-on-surface-variant">Tidak ada enrollment yang akan segera berakhir.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr class="text-label-lg text-on-surface-variant">
                                        <th>Student</th>
                                        <th>Program</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                        <th>Meetings Left</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expiringEnrollments as $enrollment)
                                    @php $daysLeft = \Carbon\Carbon::today()->diffInDays($enrollment->expiry_date, false); @endphp
                                    <tr class="{{ $daysLeft <= 3 ? 'bg-error-container' : 'bg-tertiary-fixed' }}">
                                        <td class="font-semibold">{{ $enrollment->student->user->name }}</td>
                                        <td>{{ $enrollment->program->name }}</td>
                                        <td>{{ \Carbon\Carbon::parse($enrollment->expiry_date)->format('d M Y') }}</td>
                                        <td><span class="badge {{ $daysLeft <= 3 ? 'badge-error' : 'badge-warning' }} badge-soft">{{ $daysLeft }} hari</span></td>
                                        <td>{{ $enrollment->remaining_meetings }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Unpaid Installments --}}
                <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm overflow-hidden">
                    <div class="px-lg py-4 border-b border-surface-border flex items-center gap-sm">
                        <span class="material-symbols-outlined text-error">credit_card_off</span>
                        <h3 class="text-headline-md font-semibold text-on-surface flex-1">Unpaid Installments</h3>
                        <span class="badge badge-soft">{{ $unpaidInstallments->count() }} total</span>
                    </div>
                    @if($unpaidInstallments->isEmpty())
                        <p class="p-lg text-body-md text-on-surface-variant">Semua cicilan sudah lunas.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr class="text-label-lg text-on-surface-variant">
                                        <th>Student</th>
                                        <th>Program</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unpaidInstallments as $inst)
                                    @php $isOverdue = \Carbon\Carbon::today()->isAfter($inst->due_date); @endphp
                                    <tr class="{{ $isOverdue ? 'bg-error-container' : '' }}">
                                        <td class="font-semibold">{{ $inst->enrollment->student->user->name }}</td>
                                        <td>{{ $inst->enrollment->program->name }}</td>
                                        <td>IDR {{ number_format($inst->amount) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($inst->due_date)->format('d M Y') }}</td>
                                        <td><span class="badge {{ $isOverdue ? 'badge-error' : 'badge-warning' }} badge-soft">{{ $isOverdue ? 'OVERDUE' : 'Upcoming' }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>

            {{-- Right Sidebar --}}
            <div class="lg:col-span-4 space-y-lg">

                {{-- Alerts --}}
                <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg space-y-md">
                    <h3 class="text-headline-md font-semibold text-on-surface">Peringatan Penting</h3>

                    @php $overdueCount = $unpaidInstallments->filter(fn($i) => \Carbon\Carbon::today()->isAfter($i->due_date))->count(); @endphp

                    @if($stats['pending_tutors_enrollments'] > 0)
                    <div class="flex gap-md p-md bg-error-container rounded-lg border-l-4 border-error">
                        <span class="material-symbols-outlined text-error shrink-0">warning</span>
                        <div>
                            <p class="text-body-md font-bold text-on-surface">{{ $stats['pending_tutors_enrollments'] }} Pending Assignment</p>
                            <p class="text-label-lg text-on-surface-variant mt-xs">Enrollment menunggu tutor ditetapkan.</p>
                        </div>
                    </div>
                    @endif

                    @if($overdueCount > 0)
                    <div class="flex gap-md p-md bg-error-container rounded-lg border-l-4 border-error">
                        <span class="material-symbols-outlined text-error shrink-0">credit_card_off</span>
                        <div>
                            <p class="text-body-md font-bold text-on-surface">{{ $overdueCount }} Installment Overdue</p>
                            <p class="text-label-lg text-on-surface-variant mt-xs">Cicilan melewati batas jatuh tempo.</p>
                        </div>
                    </div>
                    @endif

                    @if($expiringEnrollments->count() > 0)
                    <div class="flex gap-md p-md bg-tertiary-fixed rounded-lg border-l-4 border-warning">
                        <span class="material-symbols-outlined text-warning shrink-0">schedule</span>
                        <div>
                            <p class="text-body-md font-bold text-on-surface">{{ $expiringEnrollments->count() }} Enrollment Expiring</p>
                            <p class="text-label-lg text-on-surface-variant mt-xs">Berakhir dalam 7 hari ke depan.</p>
                        </div>
                    </div>
                    @endif

                    @if($stats['pending_tutors_enrollments'] == 0 && $overdueCount == 0 && $expiringEnrollments->count() == 0)
                    <div class="flex gap-md p-md bg-secondary-container rounded-lg border-l-4 border-secondary">
                        <span class="material-symbols-outlined text-secondary shrink-0">check_circle</span>
                        <div>
                            <p class="text-body-md font-bold text-on-surface">Semua Aman</p>
                            <p class="text-label-lg text-on-surface-variant mt-xs">Tidak ada peringatan aktif.</p>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Menu Cepat --}}
                <div class="bg-surface-container-lowest border border-surface-border rounded-lg shadow-sm p-lg">
                    <h3 class="text-headline-md font-semibold text-on-surface mb-md">Menu Cepat</h3>
                    <div class="grid grid-cols-2 gap-sm">
                        <a href="{{ route('admin.students.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">school</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Students</span>
                        </a>
                        <a href="{{ route('admin.tutors.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">person_search</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Tutors</span>
                        </a>
                        <a href="{{ route('admin.enrollments.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">how_to_reg</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Enrollments</span>
                        </a>
                        <a href="{{ route('admin.schedule.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">calendar_month</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Jadwal</span>
                        </a>
                        <a href="{{ route('admin.attendance.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">assignment_turned_in</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Absensi</span>
                        </a>
                        <a href="{{ route('admin.imports.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">upload_file</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Import</span>
                        </a>
                        <a href="{{ route('admin.programs.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">menu_book</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Programs</span>
                        </a>
                        <a href="{{ route('admin.classrooms.index') }}" class="flex flex-col items-center gap-xs p-md rounded-lg border border-surface-border hover:bg-surface-container-low transition-all text-center">
                            <span class="material-symbols-outlined text-secondary">meeting_room</span>
                            <span class="text-label-lg font-bold text-on-surface-variant uppercase">Classrooms</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>



