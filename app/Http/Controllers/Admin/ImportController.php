<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassroomKind;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Exceptions\AccountNotFoundException;
use App\Exceptions\BalanceMismatchException;
use App\Exceptions\IdempotencyException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\FixedAsset;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Rab;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\TrackerColumn;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use App\Models\TutorRate;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\DepreciationService;
use App\Services\EnrollmentLedgerService;
use App\Services\TutorAssignmentService;
use App\Support\ScheduleFormat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected EnrollmentLedgerService $ledgerService,
        protected TutorAssignmentService $tutorAssignment,
        protected DepreciationService $depreciationService,
    ) {}

    /**
     * Jalankan efek aplikasi untuk sekumpulan enrollment setelah impor:
     * bangun ulang buku besar (kas + pengakuan pendapatan dari data operasional),
     * sambungkan siswa ke tutor kelasnya, hitung ulang jam tutor.
     */
    private function runEnrollmentProcesses(array $enrollmentIds): void
    {
        $tutorIds = [];
        // Per enrollment: transaction + lockForUpdate — kontrak wajib
        // EnrollmentLedgerService::rebuild() (yang menghapus jurnal lama lalu
        // posting ulang). Kalau satu enrollment gagal, hanya transaction-nya yang
        // roll back — enrollment lain yang sudah beres tetap tersimpan.
        foreach (array_unique($enrollmentIds) as $eid) {
            $ids = DB::transaction(function () use ($eid) {
                $enrollment = Enrollment::with(['program', 'classSession'])->lockForUpdate()->find($eid);
                if (! $enrollment) {
                    return [];
                }
                $this->ledgerService->rebuild($enrollment, optional($enrollment->enrollment_date)->toDateString());

                $collected = $enrollment->tutors()->pluck('tutors.id')->all();
                if ($enrollment->class_session_id && $enrollment->classSession) {
                    $this->tutorAssignment->syncEnrollmentToClassTutors($enrollment);
                    $collected = array_merge($collected, $enrollment->classSession->tutors()->pluck('tutors.id')->all());
                }

                return $collected;
            });
            $tutorIds = array_merge($tutorIds, $ids);
        }

        $this->recomputeTutorHours($tutorIds);
    }

    /** Hitung ulang jam sejumlah tutor. */
    private function recomputeTutorHours(array $tutorIds): void
    {
        foreach (array_unique(array_map('intval', $tutorIds)) as $tid) {
            $this->tutorAssignment->recomputeAvailability($tid);
        }
    }

    public function index()
    {
        return view('admin.imports.index');
    }

    public function financeImports()
    {
        return view('admin.finance.imports');
    }

    /**
     * Parse a date string strictly (Y-m-d). Returns null if $value is empty
     * and $required is false. Throws \RuntimeException with a row-specific
     * message if the value is non-empty but unparseable, or empty while required.
     */
    private function parseDateOrFail(?string $value, int $index, string $field, bool $required = true): ?\Illuminate\Support\Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            if ($required) {
                throw new \RuntimeException("Row {$index}: kolom {$field} kosong, wajib diisi format Y-m-d.");
            }

            return null;
        }
        try {
            $parsed = \Illuminate\Support\Carbon::parse($value);
        } catch (\Exception $e) {
            throw new \RuntimeException("Row {$index}: {$field} '{$value}' format tanggal tidak valid, harus Y-m-d.");
        }

        // Tolak typo tahun yang jelas ngawur (mis. 2525, 1200). Rentang wajar
        // untuk data bisnis ini: 2015 s/d 5 tahun ke depan.
        $year = (int) $parsed->year;
        if ($year < 2015 || $year > (int) now()->year + 5) {
            throw new \RuntimeException("Row {$index}: {$field} '{$value}' tahunnya di luar rentang wajar (2015–".(now()->year + 5).') — kemungkinan typo.');
        }

        return $parsed;
    }

    public function importCOA(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 3 || trim($row[0]) === '') {
                        $errors[] = "Baris {$index}: format salah — butuh minimal kode, nama, dan tipe akun.";

                        continue;
                    }
                    $validCategories = ['cash', 'operating', 'investing', 'financing'];
                    $validTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
                    $code = trim($row[0]);
                    $type = ucfirst(strtolower(trim($row[2])));
                    if (! in_array($type, $validTypes)) {
                        $errors[] = "Baris {$index}: tipe akun '{$row[2]}' tidak dikenal. Harus salah satu dari: ".implode(', ', $validTypes).'.';

                        continue;
                    }

                    // Guardrail tabrakan: kode sudah dipakai untuk akun lain yang
                    // SUDAH ada transaksinya, dengan tipe berbeda → tolak (mengubah
                    // tipe akun yang sudah bersaldo merusak laporan). Kalau belum
                    // ada transaksinya, aman ditimpa (mis. CFO merapikan COA).
                    $existing = Account::where('code', $code)->first();
                    if ($existing && $existing->type !== $type
                        && DB::table('journal_items')->where('account_id', $existing->id)->exists()) {
                        $errors[] = "Baris {$index}: kode '{$code}' sudah dipakai akun \"{$existing->name}\" (tipe {$existing->type}) yang sudah punya transaksi — tidak boleh diubah jadi tipe {$type}. Pakai kode lain atau betulkan file.";

                        continue;
                    }

                    Account::updateOrCreate(
                        ['code' => $code],
                        [
                            'name' => trim($row[1]),
                            'type' => $type,
                            'cash_flow_category' => isset($row[3]) && in_array(trim($row[3]), $validCategories) ? trim($row[3]) : null,
                        ]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} COA entries.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importClassrooms(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (empty($row[0])) {
                        $errors[] = "Row {$index}: kolom name kosong.";

                        continue;
                    }
                    if (isset($row[1]) && ! is_numeric($row[1])) {
                        $errors[] = "Row {$index}: capacity harus angka, dapat '{$row[1]}'.";

                        continue;
                    }
                    $capacity = isset($row[1]) && is_numeric($row[1]) && (int) $row[1] > 0 ? (int) $row[1] : 1;

                    // Kolom ke-3 = jenis ruangan (physical/online/offsite). CSV
                    // lama yang isinya boolean is_at_just_speak tetap didukung:
                    // true -> physical, false -> offsite. Nama "online" -> online.
                    $raw = isset($row[2]) ? strtolower(trim($row[2])) : '';
                    $kind = match (true) {
                        in_array($raw, ClassroomKind::values(), true) => $raw,
                        str_contains(strtolower(trim($row[0])), 'online') => ClassroomKind::ONLINE->value,
                        $raw === '' || filter_var($raw, FILTER_VALIDATE_BOOLEAN) => ClassroomKind::PHYSICAL->value,
                        default => ClassroomKind::OFFSITE->value,
                    };

                    Classroom::updateOrCreate(
                        ['name' => trim($row[0])],
                        ['capacity' => $capacity, 'kind' => $kind]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} classrooms.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importPrograms(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 4) {
                        $errors[] = "Row {$index}: kurang dari 4 kolom.";

                        continue;
                    }
                    if (empty($row[0])) {
                        $errors[] = "Row {$index}: kolom name kosong.";

                        continue;
                    }
                    if (! in_array(trim($row[1]), ['private', 'semi-private', 'group'])) {
                        $errors[] = "Row {$index}: type '{$row[1]}' tidak valid, harus private / semi-private / group.";

                        continue;
                    }
                    if (! is_numeric($row[2])) {
                        $errors[] = "Row {$index}: price harus angka, dapat '{$row[2]}'.";

                        continue;
                    }
                    if (! is_numeric($row[3])) {
                        $errors[] = "Row {$index}: total_meetings harus angka, dapat '{$row[3]}'.";

                        continue;
                    }
                    Program::updateOrCreate(
                        ['name' => trim($row[0])],
                        [
                            'type' => trim($row[1]),
                            'price' => (float) $row[2],
                            'total_meetings' => (int) $row[3],
                            'min_quota' => isset($row[4]) && is_numeric($row[4]) ? (int) $row[4] : 1,
                        ]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} programs.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importTutors(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors) {
                $tutorCache = [];
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 3) {
                        $errors[] = "Row {$index}: kurang dari 3 kolom.";

                        continue;
                    }
                    if (empty($row[0])) {
                        $errors[] = "Row {$index}: kolom name kosong.";

                        continue;
                    }
                    if (empty($row[1])) {
                        $errors[] = "Row {$index}: kolom email kosong.";

                        continue;
                    }

                    $email = trim($row[1]);

                    if (! isset($tutorCache[$email])) {
                        if (User::where('email', $email)->where('role', Role::STUDENT->value)->exists()) {
                            $errors[] = "Row {$index}: email '{$email}' sudah terdaftar sebagai student, dilewati.";

                            continue;
                        }
                        // Security fix: was Hash::make('password123') — all imported
                        // tutors shared a known password. Now random per-user.
                        $user = User::firstOrCreate(
                            ['email' => $email],
                            ['name' => trim($row[0]), 'password' => Hash::make(Str::random(24)), 'phone' => trim($row[5] ?? '') ?: null]
                        );
                        // Set role explicitly (not mass-assignable per User model security)
                        if (! $user->role) {
                            $user->role = Role::TUTOR->value;
                            $user->save();
                        }
                        $tutor = Tutor::updateOrCreate(
                            ['user_id' => $user->id],
                            [
                                'persona' => trim($row[2] ?? ''),
                                'status' => trim($row[6] ?? '') ?: 'active',
                            ]
                        );
                        $tutorCache[$email] = $tutor->id;
                        $imported++;
                    }

                    $programName = trim($row[3] ?? '');
                    $rate = trim($row[4] ?? '');

                    if ($programName !== '' && is_numeric($rate)) {
                        $program = Program::where('name', $programName)->first();
                        if ($program) {
                            TutorRate::updateOrCreate(
                                ['tutor_id' => $tutorCache[$email], 'program_id' => $program->id],
                                ['rate' => (float) $rate]
                            );
                        } else {
                            $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan, rate tidak disimpan.";
                        }
                    } elseif ($programName !== '' && ! is_numeric($rate)) {
                        $errors[] = "Row {$index}: rate '{$rate}' bukan angka untuk program '{$programName}'.";
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} tutors.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importStudents(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;
        $errors = [];
        $validLevels = ['SD', 'SMP', 'SMA', 'Kuliah', 'Umum'];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors, $validLevels) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 2) {
                        $errors[] = "Row {$index}: kurang dari 2 kolom.";

                        continue;
                    }
                    if (empty($row[0])) {
                        $errors[] = "Row {$index}: kolom name kosong.";

                        continue;
                    }
                    if (empty($row[1])) {
                        $errors[] = "Row {$index}: kolom email kosong.";

                        continue;
                    }
                    $level = trim($row[4] ?? '');
                    if ($level !== '' && ! in_array($level, $validLevels)) {
                        $errors[] = "Row {$index}: education_level '{$level}' tidak valid, harus SD / SMP / SMA / Kuliah / Umum. Baris tetap diimport dengan education_level kosong.";
                        $level = '';
                    }
                    if (User::where('email', trim($row[1]))->whereIn('role', [Role::ADMIN->value, Role::TUTOR->value])->exists()) {
                        $errors[] = "Row {$index}: email '{$row[1]}' sudah terdaftar sebagai admin atau tutor, dilewati.";

                        continue;
                    }
                    // Security fix: was Hash::make('password123') — all imported
                    // students shared a known password. Now random per-user.
                    $user = User::firstOrCreate(
                        ['email' => trim($row[1])],
                        ['name' => trim($row[0]), 'password' => Hash::make(Str::random(24)), 'phone' => trim($row[3] ?? '') ?: null]
                    );
                    // Set role explicitly (not mass-assignable per User model security)
                    if (! $user->role) {
                        $user->role = Role::STUDENT->value;
                        $user->save();
                    }
                    Student::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'notes' => trim($row[2] ?? '') ?: null,
                            'education_level' => $level !== '' ? $level : null,
                        ]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} students.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importEnrollments(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $runProcesses = $request->boolean('run_processes');
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;
        $affectedIds = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors, &$affectedIds) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 11) {
                        continue;
                    }
                    [$studentEmail, $programName, $classSessionName, $enrollmentDate, $expiryDate, $paymentMethod, $paymentChannel, $totalAmount, $paymentStatus, $status, $remainingMeetings] = array_pad($row, 11, null);

                    $student = Student::whereHas('user', fn ($q) => $q->where('email', trim($studentEmail)))->first();
                    $program = Program::where('name', trim($programName))->first();

                    if (! $student) {
                        $errors[] = "Row {$index}: student '{$studentEmail}' tidak ditemukan.";

                        continue;
                    }
                    if (! $program) {
                        $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan.";

                        continue;
                    }

                    // GAP #10 fix: enrollment_date & expiry_date divalidasi eksplisit
                    // sebelum dipakai, supaya format salah menghasilkan pesan error
                    // per-baris (lalu skip baris) bukan exception mentah yang
                    // membatalkan seluruh request di tengah jalan.
                    try {
                        $parsedEnrollmentDate = $this->parseDateOrFail($enrollmentDate, $index, 'enrollment_date', true);
                        $parsedExpiryDate = $this->parseDateOrFail($expiryDate, $index, 'expiry_date', false);
                    } catch (\RuntimeException $e) {
                        $errors[] = $e->getMessage();

                        continue;
                    }

                    $classSession = $classSessionName ? ClassSession::where('name', trim($classSessionName))->first() : null;

                    $validPaymentMethods = ['full upfront', 'installment'];
                    $validPaymentChannels = ['cash', 'bank'];
                    $validStatuses = ['active', 'graduate', 'expired', 'cancelled', 'waitlist'];
                    $validPaymentStatuses = ['pending', 'partial', 'full'];

                    if (! in_array(trim($paymentMethod), $validPaymentMethods)) {
                        $errors[] = "Row {$index}: payment_method '{$paymentMethod}' tidak valid, harus: ".implode(' / ', $validPaymentMethods).'.';

                        continue;
                    }
                    if (trim($paymentChannel) !== '' && ! in_array(trim($paymentChannel), $validPaymentChannels)) {
                        $errors[] = "Row {$index}: payment_channel '{$paymentChannel}' tidak valid, harus: ".implode(' / ', $validPaymentChannels).'.';

                        continue;
                    }
                    if (! in_array(trim($status), $validStatuses)) {
                        $errors[] = "Row {$index}: status '{$status}' tidak valid, harus: ".implode(' / ', $validStatuses).'.';

                        continue;
                    }
                    if (! in_array(trim($paymentStatus), $validPaymentStatuses)) {
                        $errors[] = "Row {$index}: payment_status '{$paymentStatus}' tidak valid, harus: ".implode(' / ', $validPaymentStatuses).'.';

                        continue;
                    }

                    $enrollment = Enrollment::updateOrCreate(
                        ['student_id' => $student->id, 'program_id' => $program->id, 'enrollment_date' => $parsedEnrollmentDate->toDateString()],
                        [
                            'class_session_id' => $classSession?->id,
                            'expiry_date' => $parsedExpiryDate?->toDateString(),
                            'payment_method' => trim($paymentMethod),
                            'payment_channel' => trim($paymentChannel) ?: null,
                            'total_amount' => (float) $totalAmount,
                            'payment_status' => trim($paymentStatus) ?: 'pending',
                            'status' => trim($status) ?: 'active',
                            'remaining_meetings' => (int) $remainingMeetings ?: $program->total_meetings,
                        ]
                    );
                    $affectedIds[] = $enrollment->id;
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        if ($runProcesses && $affectedIds) {
            $this->runEnrollmentProcesses($affectedIds);
        }

        $msg = "Imported: {$imported} enrollments.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        $flash = back()->with($errors ? 'error' : 'success', $msg);
        if (! $runProcesses) {
            $flash->with('warning', '⚠️ Anda memilih "data mentah saja" — jurnal akuntansi & sambungan tutor TIDAK dibuat. Impor jurnal terpisah, atau ulangi impor ini dengan pilihan "jalankan proses aplikasi" dicentang.');
        }

        return $flash;
    }

    public function importInstallments(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $runProcesses = $request->boolean('run_processes');
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;
        $affectedIds = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors, &$affectedIds) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 4) {
                        continue;
                    }
                    [$studentEmail, $programName, $amount, $dueDate, $paidAt, $paymentChannel] = array_pad($row, 6, null);

                    $student = Student::whereHas('user', fn ($q) => $q->where('email', trim($studentEmail)))->first();
                    $program = Program::where('name', trim($programName))->first();

                    if (! $student) {
                        $errors[] = "Row {$index}: student '{$studentEmail}' tidak ditemukan.";

                        continue;
                    }
                    if (! $program) {
                        $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan.";

                        continue;
                    }

                    $enrollment = Enrollment::where('student_id', $student->id)->where('program_id', $program->id)->latest()->first();
                    if (! $enrollment) {
                        $errors[] = "Row {$index}: enrollment tidak ditemukan untuk '{$studentEmail}' - '{$programName}'.";

                        continue;
                    }

                    // GAP #10 fix: due_date & paid_at divalidasi eksplisit
                    try {
                        $parsedDueDate = $this->parseDateOrFail($dueDate, $index, 'due_date', false);
                        $parsedPaidAt = $this->parseDateOrFail($paidAt, $index, 'paid_at', false);
                    } catch (\RuntimeException $e) {
                        $errors[] = $e->getMessage();

                        continue;
                    }

                    Installment::updateOrCreate(
                        ['enrollment_id' => $enrollment->id, 'due_date' => $parsedDueDate?->toDateString()],
                        [
                            'amount' => (float) $amount,
                            'paid_at' => $parsedPaidAt,
                            'payment_channel' => trim($paymentChannel) ?: null,
                        ]
                    );

                    $unpaidCount = Installment::where('enrollment_id', $enrollment->id)
                        ->whereNull('paid_at')
                        ->count();
                    $enrollment->update([
                        'payment_status' => $unpaidCount === 0
                            ? PaymentStatus::FULL->value
                            : PaymentStatus::PARTIAL->value,
                    ]);

                    $affectedIds[] = $enrollment->id;
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        if ($runProcesses && $affectedIds) {
            foreach (array_unique($affectedIds) as $eid) {
                DB::transaction(function () use ($eid) {
                    $enrollment = Enrollment::with('program')->lockForUpdate()->find($eid);
                    if ($enrollment) {
                        $this->ledgerService->rebuild($enrollment, optional($enrollment->enrollment_date)->toDateString());
                    }
                });
            }
        }

        $msg = "Imported: {$imported} installments.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }
        if (! $runProcesses) {
            $msg .= ' (Data mentah saja — jurnal penerimaan kas tidak dibuat.)';
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importSchedules(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $runProcesses = $request->boolean('run_processes');
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;
        $tutorIds = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors, &$tutorIds) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 5) {
                        continue;
                    }
                    [$studentEmail, $programName, $classroomName, $day, $timeBlock, $classSessionName] = array_pad($row, 6, null);

                    $student = Student::whereHas('user', fn ($q) => $q->where('email', trim($studentEmail)))->first();
                    $program = Program::where('name', trim($programName))->first();
                    $classroom = Classroom::where('name', trim($classroomName))->first();

                    if (! $student) {
                        $errors[] = "Row {$index}: student '{$studentEmail}' tidak ditemukan.";

                        continue;
                    }
                    if (! $program) {
                        $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan.";

                        continue;
                    }
                    if (! $classroom) {
                        $errors[] = "Row {$index}: classroom '{$classroomName}' tidak ditemukan.";

                        continue;
                    }

                    $enrollment = Enrollment::where('student_id', $student->id)->where('program_id', $program->id)->latest()->first();
                    if (! $enrollment) {
                        $errors[] = "Row {$index}: enrollment tidak ditemukan.";

                        continue;
                    }

                    $classSession = $classSessionName ? ClassSession::where('name', trim($classSessionName))->first() : null;

                    Schedule::updateOrCreate(
                        [
                            'enrollment_id' => $enrollment->id,
                            'day' => ScheduleFormat::day($day),
                            'time_block' => ScheduleFormat::timeBlock($timeBlock),
                        ],
                        [
                            'classroom_id' => $classroom->id,
                            'class_session_id' => $classSession?->id,
                        ]
                    );
                    if ($classSession) {
                        $tutorIds = array_merge($tutorIds, $classSession->tutors()->pluck('tutors.id')->all());
                    }
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        if ($runProcesses && $tutorIds) {
            $this->recomputeTutorHours($tutorIds);
        }

        $msg = "Imported: {$imported} schedules.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importTutorAvailability(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $runProcesses = $request->boolean('run_processes');
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;
        $tutorIds = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors, &$tutorIds) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 3) {
                        continue;
                    }
                    [$tutorEmail, $day, $timeBlock, $status] = array_pad($row, 4, 'available');

                    $tutor = Tutor::whereHas('user', fn ($q) => $q->where('email', trim($tutorEmail)))->first();
                    if (! $tutor) {
                        $errors[] = "Row {$index}: tutor '{$tutorEmail}' tidak ditemukan.";

                        continue;
                    }

                    TutorAvailability::updateOrCreate(
                        [
                            'tutor_id' => $tutor->id,
                            'day' => ScheduleFormat::day($day),
                            'time_block' => ScheduleFormat::timeBlock($timeBlock),
                        ],
                        ['status' => trim($status) ?: 'available']
                    );
                    $tutorIds[] = $tutor->id;
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        if ($runProcesses && $tutorIds) {
            $this->recomputeTutorHours($tutorIds);
        }

        $msg = "Imported: {$imported} tutor availability.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importClassSessions(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;

        try {
            DB::transaction(function () use ($data, &$imported, &$errors) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 3) {
                        continue;
                    }
                    [$name, $programName, $classType, $status] = array_pad($row, 4, null);

                    $program = Program::where('name', trim($programName))->first();
                    if (! $program) {
                        $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan.";

                        continue;
                    }

                    ClassSession::updateOrCreate(
                        ['name' => trim($name), 'program_id' => $program->id],
                        [
                            'class_type' => trim($classType) ?: 'private',
                            'status' => trim($status) ?: 'active',
                        ]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} class sessions.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importRabs(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;

        try {
            DB::transaction(function () use ($data, &$imported, &$errors) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 5) {
                        continue;
                    }
                    [$year, $division, $accountName, $accountCode, $activity, $q1, $q2, $q3, $q4] = array_pad($row, 9, 0);

                    Rab::updateOrCreate(
                        ['year' => trim($year), 'division' => trim($division), 'account_code' => trim($accountCode), 'activity' => trim($activity)],
                        [
                            'account_name' => trim($accountName),
                            'q1' => (int) $q1,
                            'q2' => (int) $q2,
                            'q3' => (int) $q3,
                            'q4' => (int) $q4,
                        ]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        $msg = "Imported: {$imported} RAB entries.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importFixedAssets(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $runProcesses = $request->boolean('run_processes');
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = [];
        $imported = 0;
        $assetIds = [];

        try {
            DB::transaction(function () use ($data, &$imported, &$errors, &$assetIds) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 6) {
                        continue;
                    }
                    [$name, $category, $acquiredAt, $cost, $salvageValue, $usefulLife, $depreciationMethod, $notes, $expenseAccountCode, $accumulatedAccountCode, $isActive] = array_pad($row, 11, null);

                    // GAP #10 + GAP #11 fix: acquired_at wajib diisi dan divalidasi
                    // eksplisit. Sebelumnya string mentah (termasuk string kosong)
                    // diserahkan langsung ke Eloquent date-cast: string kosong
                    // silently jadi "hari ini" (bikin masa pakai aset salah
                    // dihitung), sedangkan string tak valid melempar exception
                    // mentah yang membatalkan seluruh proses import di tengah jalan.
                    try {
                        $parsedAcquiredAt = $this->parseDateOrFail($acquiredAt, $index, 'acquired_at', true);
                    } catch (\RuntimeException $e) {
                        $errors[] = $e->getMessage();

                        continue;
                    }

                    if (! is_numeric($cost)) {
                        $errors[] = "Row {$index}: cost harus angka, dapat '{$cost}'.";

                        continue;
                    }
                    if (! is_numeric($salvageValue)) {
                        $errors[] = "Row {$index}: salvage_value harus angka, dapat '{$salvageValue}'.";

                        continue;
                    }
                    if (! is_numeric($usefulLife)) {
                        $errors[] = "Row {$index}: useful_life harus angka, dapat '{$usefulLife}'.";

                        continue;
                    }

                    $expenseAccount = $expenseAccountCode ? Account::where('code', trim($expenseAccountCode))->first() : null;
                    $accumulatedAccount = $accumulatedAccountCode ? Account::where('code', trim($accumulatedAccountCode))->first() : null;

                    if ($expenseAccountCode && ! $expenseAccount) {
                        $errors[] = "Row {$index}: expense account '{$expenseAccountCode}' tidak ditemukan.";

                        continue;
                    }
                    if ($accumulatedAccountCode && ! $accumulatedAccount) {
                        $errors[] = "Row {$index}: accumulated account '{$accumulatedAccountCode}' tidak ditemukan.";

                        continue;
                    }

                    $asset = FixedAsset::updateOrCreate(
                        ['name' => trim($name)],
                        [
                            'category' => trim($category),
                            'acquired_at' => $parsedAcquiredAt->toDateString(),
                            'cost' => (float) $cost,
                            'salvage_value' => (float) $salvageValue,
                            'useful_life' => (int) $usefulLife,
                            'depreciation_method' => trim($depreciationMethod) ?: 'straight_line',
                            'notes' => trim($notes) ?: null,
                            'expense_account_id' => $expenseAccount?->id,
                            'accumulated_account_id' => $accumulatedAccount?->id,
                            'is_active' => filter_var($isActive ?? true, FILTER_VALIDATE_BOOLEAN),
                        ]
                    );
                    $assetIds[] = $asset->id;
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        if ($runProcesses && $assetIds) {
            foreach (FixedAsset::whereIn('id', array_unique($assetIds))->get() as $asset) {
                $this->depreciationService->rebuildAsset($asset);
            }
        }

        $msg = "Imported: {$imported} fixed assets.";
        if ($errors) {
            $msg .= ' Errors: '.implode(' | ', $errors);
        }
        if (! $runProcesses) {
            $msg .= ' (Data mentah saja — jurnal penyusutan tidak dibuat.)';
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importTrackerColumns(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;

        try {
            DB::transaction(function () use ($data, &$imported) {
                foreach ($data as $index => $row) {
                    if ($index === 0) {
                        continue;
                    }
                    if (count($row) < 1) {
                        continue;
                    }
                    [$name, $order] = array_pad($row, 2, 0);

                    TrackerColumn::updateOrCreate(
                        ['name' => trim($name)],
                        ['order' => (int) $order]
                    );
                    $imported++;
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Import dibatalkan total (rollback), tidak ada data tersimpan: '.$e->getMessage());
        }

        return back()->with('success', "Imported: {$imported} tracker columns.");
    }

    public function importJournals(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));

        $grouped = [];
        foreach ($data as $index => $row) {
            if ($index === 0) {
                continue;
            }
            if (count($row) < 6) {
                continue;
            }
            [$date, $description, $reference, $accountCode, $debit, $credit] = $row;
            $grouped[$reference][] = [
                'date' => trim($date),
                'description' => trim($description),
                'account_code' => trim($accountCode),
                'debit' => (float) $debit,
                'credit' => (float) $credit,
            ];
        }

        $imported = 0;
        $skipped = [];
        $errors = [];

        foreach ($grouped as $reference => $lines) {
            $date = $lines[0]['date'];
            $description = $lines[0]['description'];

            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            } catch (\Exception $e) {
                $errors[] = "{$reference}: format tanggal tidak valid ({$date}), harus Y-m-d.";

                continue;
            }
            // Jurnal = catatan kejadian yang SUDAH terjadi. Tanggal masa depan
            // atau tahun ngawur (typo mis. 2525) ditolak.
            if ($parsedDate->year < 2015 || $parsedDate->isAfter(now()->endOfDay())) {
                $errors[] = "{$reference}: tanggal jurnal '{$date}' tidak masuk akal (masa depan / typo tahun).";

                continue;
            }

            $hasInvalidRow = false;
            foreach ($lines as $line) {
                if (empty($line['account_code'])) {
                    $errors[] = "{$reference}: account_code kosong.";
                    $hasInvalidRow = true;
                    break;
                }
                if (! is_numeric($line['debit']) || ! is_numeric($line['credit'])) {
                    $errors[] = "{$reference}: debit/credit bukan angka.";
                    $hasInvalidRow = true;
                    break;
                }
                if ($line['debit'] < 0 || $line['credit'] < 0) {
                    $errors[] = "{$reference}: debit/credit tidak boleh negatif.";
                    $hasInvalidRow = true;
                    break;
                }
            }
            if ($hasInvalidRow) {
                continue;
            }

            $items = array_map(fn ($l) => [
                'account_code' => $l['account_code'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ], $lines);

            try {
                $this->accountingService->createJournal($date, $description, $reference, $items);
                $imported++;
            } catch (IdempotencyException $e) {
                $skipped[] = $reference;
            } catch (BalanceMismatchException $e) {
                $td = array_sum(array_column($items, 'debit'));
                $tc = array_sum(array_column($items, 'credit'));
                $errors[] = "{$reference}: total debit (".number_format($td, 0, ',', '.').') tidak sama dengan total kredit ('
                    .number_format($tc, 0, ',', '.').'). Selisih '.number_format(abs($td - $tc), 0, ',', '.').'.';
            } catch (AccountNotFoundException $e) {
                $errors[] = "{$reference}: ".$e->getMessage().' Impor / betulkan Chart of Accounts dulu.';
            } catch (\Exception $e) {
                $errors[] = "{$reference}: ".$e->getMessage();
            }
        }

        $msg = "Berhasil impor {$imported} jurnal.";
        if ($skipped) {
            $msg .= ' Dilewati karena referensinya sudah ada: '.implode(', ', $skipped).'.';
        }
        if ($errors) {
            $msg .= ' Gagal: '.implode(' | ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }
}
