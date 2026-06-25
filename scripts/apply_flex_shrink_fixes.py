#!/usr/bin/env python3
"""Apply flex-shrink-0 + min-w-0 shrink truncate fixes across 13 view files.

Pattern: page header rows with title (left) + button(s) (right) need:
  - Container: gap-md added if missing
  - Title or left wrapper: min-w-0 shrink (truncate for plain h1/h3 titles)
  - Right button/div: flex-shrink-0

Without these, long titles push buttons off-screen or wrap awkwardly.
"""
from pathlib import Path

# Each entry: (file_path, old_string, new_string)
FIXES = [
    # 1. adjusting-journals/index
    (
        "resources/views/admin/adjusting-journals/index.blade.php",
        '''        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface">Akumulasi Jurnal Penyesuaian</h3>
            <div class="flex gap-sm">''',
        '''        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface min-w-0 shrink truncate">Akumulasi Jurnal Penyesuaian</h3>
            <div class="flex gap-sm flex-shrink-0">''',
    ),
    # 2. accounts/index
    (
        "resources/views/admin/accounts/index.blade.php",
        '''        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface">Chart of Accounts</h3>
            <div class="flex gap-sm">''',
        '''        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface min-w-0 shrink truncate">Chart of Accounts</h3>
            <div class="flex gap-sm flex-shrink-0">''',
    ),
    # 3. journals/index
    (
        "resources/views/admin/journals/index.blade.php",
        '''        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface">Journals</h3>
            <div class="flex gap-sm">''',
        '''        <div class="flex items-center justify-between gap-md">
            <h3 class="text-headline-lg font-semibold text-on-surface min-w-0 shrink truncate">Journals</h3>
            <div class="flex gap-sm flex-shrink-0">''',
    ),
    # 4. payroll/index
    (
        "resources/views/admin/finance/payroll/index.blade.php",
        '''    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Payroll</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Generate dan approve pembayaran tutor per bulan</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-create').showModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90">''',
        '''    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Payroll</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Generate dan approve pembayaran tutor per bulan</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-create').showModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90 flex-shrink-0">''',
    ),
    # 5. classrooms/index — header + button class
    (
        "resources/views/admin/classrooms/index.blade.php",
        '''        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-headline-lg font-semibold text-on-surface">Classrooms</h3>
                <p class="text-body-md text-on-surface-variant mt-xs">Kelola ruang kelas yang tersedia.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create').showModal()"''',
        '''        <div class="flex items-center justify-between gap-md">
            <div class="min-w-0 shrink">
                <h3 class="text-headline-lg font-semibold text-on-surface">Classrooms</h3>
                <p class="text-body-md text-on-surface-variant mt-xs">Kelola ruang kelas yang tersedia.</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-create').showModal()"''',
    ),
    (
        "resources/views/admin/classrooms/index.blade.php",
        '''                class="btn bg-secondary text-on-secondary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Classroom''',
        '''                class="btn bg-secondary text-on-secondary border-none hover:opacity-90 gap-sm flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Classroom''',
    ),
    # 6. class_sessions/index — header + link class
    (
        "resources/views/admin/class_sessions/index.blade.php",
        '''        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-headline-lg font-semibold text-on-surface">Class Sessions</h3>
                <p class="text-body-md text-on-surface-variant">{{ $classSessions->count() }} kelas terdaftar</p>
            </div>
            <a href="{{ route('admin.class-sessions.create') }}"''',
        '''        <div class="flex items-center justify-between gap-md">
            <div class="min-w-0 shrink">
                <h3 class="text-headline-lg font-semibold text-on-surface">Class Sessions</h3>
                <p class="text-body-md text-on-surface-variant">{{ $classSessions->count() }} kelas terdaftar</p>
            </div>
            <a href="{{ route('admin.class-sessions.create') }}"''',
    ),
    (
        "resources/views/admin/class_sessions/index.blade.php",
        '''                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Kelas''',
        '''                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Kelas''',
    ),
    # 7. tutors/index — header + button class
    (
        "resources/views/admin/tutors/index.blade.php",
        '''        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-headline-md font-semibold text-on-surface">Tutors</h3>
                <p class="text-body-md text-on-surface-variant">{{ $tutors->count() }} tutor terdaftar &mdash; <span class="text-success font-semibold">{{ $activeTutorCount }} aktif</span></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-tutor').showModal()"''',
        '''        <div class="flex items-center justify-between gap-md">
            <div class="min-w-0 shrink">
                <h3 class="text-headline-md font-semibold text-on-surface">Tutors</h3>
                <p class="text-body-md text-on-surface-variant">{{ $tutors->count() }} tutor terdaftar &mdash; <span class="text-success font-semibold">{{ $activeTutorCount }} aktif</span></p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-tutor').showModal()"''',
    ),
    (
        "resources/views/admin/tutors/index.blade.php",
        '''                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Tutor''',
        '''                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Tutor''',
    ),
    # 8. students/tracker — header + button class
    (
        "resources/views/admin/students/tracker.blade.php",
        '''        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-headline-md font-semibold text-on-surface">Student Tracker</h3>
                <p class="text-body-md text-on-surface-variant">{{ $students->count() }} siswa terdaftar</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-column').showModal()"''',
        '''        <div class="flex items-center justify-between gap-md">
            <div class="min-w-0 shrink">
                <h3 class="text-headline-md font-semibold text-on-surface">Student Tracker</h3>
                <p class="text-body-md text-on-surface-variant">{{ $students->count() }} siswa terdaftar</p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-column').showModal()"''',
    ),
    (
        "resources/views/admin/students/tracker.blade.php",
        '''                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Kolom''',
        '''                class="btn bg-primary-container text-on-primary border-none hover:opacity-90 gap-sm flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tambah Kolom''',
    ),
    # 9. tutor/availability/index
    (
        "resources/views/tutor/availability/index.blade.php",
        '''    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-headline-lg font-semibold text-on-surface">Availabilitas</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Atur slot waktu yang bisa dijadwalkan admin</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-add').showModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90">''',
        '''    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-headline-lg font-semibold text-on-surface">Availabilitas</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Atur slot waktu yang bisa dijadwalkan admin</p>
        </div>
        <button type="button" onclick="document.getElementById('modal-add').showModal()"
            class="btn bg-primary-container text-on-primary border-none hover:opacity-90 flex-shrink-0">''',
    ),
    # 10. tutor/practice/index
    (
        "resources/views/tutor/practice/index.blade.php",
        '''    <div class="flex items-center justify-between">
        <h1 class="text-headline-lg font-semibold text-on-surface">Tugas Practice</h1>
        <a href="{{ route('tutor.practice.create') }}" class="btn bg-primary-container text-on-primary border-none hover:opacity-90">''',
        '''    <div class="flex items-center justify-between gap-md">
        <h1 class="text-headline-lg font-semibold text-on-surface min-w-0 shrink truncate">Tugas Practice</h1>
        <a href="{{ route('tutor.practice.create') }}" class="btn bg-primary-container text-on-primary border-none hover:opacity-90 flex-shrink-0">''',
    ),
    # 11. reports/balance_sheet
    (
        "resources/views/admin/reports/balance_sheet.blade.php",
        '''    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Balance Sheet</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Posisi keuangan per tanggal tertentu</p>
        </div>
        <div class="flex gap-sm">''',
        '''    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Balance Sheet</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Posisi keuangan per tanggal tertentu</p>
        </div>
        <div class="flex gap-sm flex-shrink-0">''',
    ),
    # 12. reports/profit_loss
    (
        "resources/views/admin/reports/profit_loss.blade.php",
        '''    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Profit & Loss</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan dan beban dalam periode tertentu</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">''',
        '''    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Profit & Loss</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan dan beban dalam periode tertentu</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border flex-shrink-0">''',
    ),
    # 13. reports/trial_balance
    (
        "resources/views/admin/reports/trial_balance.blade.php",
        '''    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Trial Balance</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Saldo semua akun dari seluruh jurnal</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">''',
        '''    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Trial Balance</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Saldo semua akun dari seluruh jurnal</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border flex-shrink-0">''',
    ),
    # 14. reports/deferred_revenue
    (
        "resources/views/admin/reports/deferred_revenue.blade.php",
        '''    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-on-surface">Deferred Revenue</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan yang belum diakui dari enrollment aktif</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border">''',
        '''    <div class="flex items-center justify-between gap-md">
        <div class="min-w-0 shrink">
            <h1 class="text-xl font-semibold text-on-surface">Deferred Revenue</h1>
            <p class="text-sm text-on-surface-variant mt-xs">Pendapatan yang belum diakui dari enrollment aktif</p>
        </div>
        <button type="button" onclick="exportCSV()" class="btn btn-sm btn-ghost border border-surface-border flex-shrink-0">''',
    ),
]


def main():
    total_applied = 0
    total_skipped = 0
    files_touched = set()
    for file_path, old, new in FIXES:
        p = Path(file_path)
        if not p.exists():
            print(f"  ✗ FILE NOT FOUND: {file_path}")
            total_skipped += 1
            continue
        content = p.read_text(encoding="utf-8")
        if old in content:
            new_content = content.replace(old, new, 1)
            p.write_text(new_content, encoding="utf-8")
            print(f"  ✓ {file_path}")
            total_applied += 1
            files_touched.add(file_path)
        elif new in content:
            print(f"  • already applied: {file_path}")
            total_skipped += 1
        else:
            print(f"  ✗ pattern not found: {file_path}")
            total_skipped += 1

    print()
    print(f"Applied: {total_applied} fix(es) across {len(files_touched)} file(s)")
    print(f"Skipped: {total_skipped}")


if __name__ == "__main__":
    main()
