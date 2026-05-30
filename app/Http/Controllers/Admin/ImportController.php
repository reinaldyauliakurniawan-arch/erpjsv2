<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\Student;
use App\Models\User;
use App\Models\Journal;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Schedule;
use App\Models\ClassSession;
use App\Models\TutorAvailability;
use App\Models\FixedAsset;
use App\Models\Rab;
use App\Models\TrackerColumn;
use App\Models\Attendance;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(protected AccountingService $accountingService) {}

    public function index()
    {
        return view('admin.imports.index');
    }

    public function financeImports()
    {
        return view('admin.finance.imports');
    }

    public function importCOA(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 3) continue;
            $validCategories = ['cash', 'operating', 'investing', 'financing'];
            $validTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
            $type = ucfirst(strtolower(trim($row[2])));
            if (!in_array($type, $validTypes)) continue;
            Account::updateOrCreate(
                ['code' => trim($row[0])],
                [
                    'name'               => trim($row[1]),
                    'type'               => $type,
                    'cash_flow_category' => isset($row[3]) && in_array(trim($row[3]), $validCategories) ? trim($row[3]) : null,
                ]
            );
        }
        return back()->with('success', 'COA imported successfully.');
    }

    public function importClassrooms(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            Classroom::updateOrCreate(
                ['name' => $row[0]],
                ['capacity' => $row[1]]
            );
        }
        return back()->with('success', 'Classrooms imported successfully.');
    }

    public function importPrograms(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 4) continue;
            if (!in_array(trim($row[1]), ['private', 'semi-private', 'group'])) continue;
            Program::updateOrCreate(
                ['name' => $row[0]],
                [
                    'type'           => $row[1],
                    'price'          => (float) $row[2],
                    'total_meetings' => (int) $row[3],
                    'min_quota'      => isset($row[4]) && is_numeric($row[4]) ? (int) $row[4] : 1,
                ]
            );
        }
        return back()->with('success', 'Programs imported successfully.');
    }

    public function importTutors(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));

        $tutorCache = [];

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 3) continue;
            if (empty($row[0]) || empty($row[1])) continue;

            $email = trim($row[1]);

            if (!isset($tutorCache[$email])) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    ['name' => trim($row[0]), 'password' => Hash::make('password123'), 'role' => 'tutor']
                );
                $tutor = Tutor::updateOrCreate(
                    ['user_id' => $user->id],
                    ['persona' => trim($row[2] ?? '')]
                );
                $tutorCache[$email] = $tutor->id;
            }

            $programName = trim($row[3] ?? '');
            $rate        = trim($row[4] ?? '');

            if ($programName !== '' && is_numeric($rate)) {
                $program = Program::where('name', $programName)->first();
                if ($program) {
                    TutorRate::updateOrCreate(
                        ['tutor_id' => $tutorCache[$email], 'program_id' => $program->id],
                        ['rate' => (float) $rate]
                    );
                }
            }
        }

        return back()->with('success', 'Tutors imported successfully.');
    }

    public function importStudents(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 2) continue;
            $user = User::firstOrCreate(
                ['email' => $row[1]],
                ['name' => $row[0], 'password' => Hash::make('password123'), 'role' => 'student']
            );
            Student::updateOrCreate(['user_id' => $user->id], ['notes' => $row[2] ?? null]);
        }
        return back()->with('success', 'Students imported successfully.');
    }

    public function importEnrollments(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 11) continue;
            [$studentEmail, $programName, $classSessionName, $enrollmentDate, $expiryDate, $paymentMethod, $paymentChannel, $totalAmount, $paymentStatus, $status, $remainingMeetings] = array_pad($row, 11, null);

            $student = Student::whereHas('user', fn($q) => $q->where('email', trim($studentEmail)))->first();
            $program = Program::where('name', trim($programName))->first();

            if (!$student) { $errors[] = "Row {$index}: student '{$studentEmail}' tidak ditemukan."; continue; }
            if (!$program) { $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan."; continue; }

            $classSession = $classSessionName ? ClassSession::where('name', trim($classSessionName))->first() : null;

            Enrollment::updateOrCreate(
                ['student_id' => $student->id, 'program_id' => $program->id, 'enrollment_date' => trim($enrollmentDate)],
                [
                    'class_session_id'   => $classSession?->id,
                    'expiry_date'        => trim($expiryDate) ?: null,
                    'payment_method'     => trim($paymentMethod),
                    'payment_channel'    => trim($paymentChannel) ?: null,
                    'total_amount'       => (float) $totalAmount,
                    'payment_status'     => trim($paymentStatus) ?: 'pending',
                    'status'             => trim($status) ?: 'active',
                    'remaining_meetings' => (int) $remainingMeetings ?: $program->total_meetings,
                ]
            );
            $imported++;
        }

        $msg = "Imported: {$imported} enrollments.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importInstallments(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 4) continue;
            [$studentEmail, $programName, $amount, $dueDate, $paidAt, $paymentChannel] = array_pad($row, 6, null);

            $student = Student::whereHas('user', fn($q) => $q->where('email', trim($studentEmail)))->first();
            $program = Program::where('name', trim($programName))->first();

            if (!$student) { $errors[] = "Row {$index}: student '{$studentEmail}' tidak ditemukan."; continue; }
            if (!$program) { $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan."; continue; }

            $enrollment = Enrollment::where('student_id', $student->id)->where('program_id', $program->id)->latest()->first();
            if (!$enrollment) { $errors[] = "Row {$index}: enrollment tidak ditemukan untuk '{$studentEmail}' - '{$programName}'."; continue; }

            Installment::updateOrCreate(
                ['enrollment_id' => $enrollment->id, 'due_date' => trim($dueDate) ?: null],
                [
                    'amount'          => (float) $amount,
                    'paid_at'         => trim($paidAt) ?: null,
                    'payment_channel' => trim($paymentChannel) ?: null,
                ]
            );
            $imported++;
        }

        $msg = "Imported: {$imported} installments.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importSchedules(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 5) continue;
            [$studentEmail, $programName, $classroomName, $day, $timeBlock, $classSessionName] = array_pad($row, 6, null);

            $student   = Student::whereHas('user', fn($q) => $q->where('email', trim($studentEmail)))->first();
            $program   = Program::where('name', trim($programName))->first();
            $classroom = Classroom::where('name', trim($classroomName))->first();

            if (!$student)   { $errors[] = "Row {$index}: student '{$studentEmail}' tidak ditemukan."; continue; }
            if (!$program)   { $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan."; continue; }
            if (!$classroom) { $errors[] = "Row {$index}: classroom '{$classroomName}' tidak ditemukan."; continue; }

            $enrollment = Enrollment::where('student_id', $student->id)->where('program_id', $program->id)->latest()->first();
            if (!$enrollment) { $errors[] = "Row {$index}: enrollment tidak ditemukan."; continue; }

            $classSession = $classSessionName ? ClassSession::where('name', trim($classSessionName))->first() : null;

            Schedule::updateOrCreate(
                ['enrollment_id' => $enrollment->id, 'day' => trim($day), 'time_block' => trim($timeBlock)],
                [
                    'classroom_id'     => $classroom->id,
                    'class_session_id' => $classSession?->id,
                ]
            );
            $imported++;
        }

        $msg = "Imported: {$imported} schedules.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importTutorAvailability(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 3) continue;
            [$tutorEmail, $day, $timeBlock, $status] = array_pad($row, 4, 'available');

            $tutor = Tutor::whereHas('user', fn($q) => $q->where('email', trim($tutorEmail)))->first();
            if (!$tutor) { $errors[] = "Row {$index}: tutor '{$tutorEmail}' tidak ditemukan."; continue; }

            TutorAvailability::updateOrCreate(
                ['tutor_id' => $tutor->id, 'day' => trim($day), 'time_block' => trim($timeBlock)],
                ['status' => trim($status) ?: 'available']
            );
            $imported++;
        }

        $msg = "Imported: {$imported} tutor availability.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importClassSessions(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 3) continue;
            [$name, $programName, $classType, $status] = array_pad($row, 4, null);

            $program = Program::where('name', trim($programName))->first();
            if (!$program) { $errors[] = "Row {$index}: program '{$programName}' tidak ditemukan."; continue; }

            ClassSession::updateOrCreate(
                ['name' => trim($name), 'program_id' => $program->id],
                [
                    'class_type' => trim($classType) ?: 'private',
                    'status'     => trim($status) ?: 'active',
                ]
            );
            $imported++;
        }

        $msg = "Imported: {$imported} class sessions.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importRabs(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 5) continue;
            [$year, $division, $accountName, $accountCode, $activity, $q1, $q2, $q3, $q4] = array_pad($row, 9, 0);

            Rab::updateOrCreate(
                ['year' => trim($year), 'division' => trim($division), 'account_code' => trim($accountCode), 'activity' => trim($activity)],
                [
                    'account_name' => trim($accountName),
                    'q1'           => (int) $q1,
                    'q2'           => (int) $q2,
                    'q3'           => (int) $q3,
                    'q4'           => (int) $q4,
                ]
            );
            $imported++;
        }

        $msg = "Imported: {$imported} RAB entries.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importFixedAssets(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $errors = []; $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 6) continue;
            [$name, $category, $acquiredAt, $cost, $salvageValue, $usefulLife, $depreciationMethod, $notes, $expenseAccountCode, $accumulatedAccountCode, $isActive] = array_pad($row, 11, null);

            $expenseAccount     = $expenseAccountCode ? \App\Models\Account::where('code', trim($expenseAccountCode))->first() : null;
            $accumulatedAccount = $accumulatedAccountCode ? \App\Models\Account::where('code', trim($accumulatedAccountCode))->first() : null;

            if ($expenseAccountCode && !$expenseAccount) { $errors[] = "Row {$index}: expense account '{$expenseAccountCode}' tidak ditemukan."; continue; }
            if ($accumulatedAccountCode && !$accumulatedAccount) { $errors[] = "Row {$index}: accumulated account '{$accumulatedAccountCode}' tidak ditemukan."; continue; }

            FixedAsset::updateOrCreate(
                ['name' => trim($name)],
                [
                    'category'               => trim($category),
                    'acquired_at'            => trim($acquiredAt),
                    'cost'                   => (float) $cost,
                    'salvage_value'          => (float) $salvageValue,
                    'useful_life'            => (int) $usefulLife,
                    'depreciation_method'    => trim($depreciationMethod) ?: 'straight_line',
                    'notes'                  => trim($notes) ?: null,
                    'expense_account_id'     => $expenseAccount?->id,
                    'accumulated_account_id' => $accumulatedAccount?->id,
                    'is_active'              => filter_var($isActive ?? true, FILTER_VALIDATE_BOOLEAN),
                ]
            );
            $imported++;
        }

        $msg = "Imported: {$imported} fixed assets.";
        if ($errors) $msg .= ' Errors: ' . implode(' | ', $errors);
        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function importTrackerColumns(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $path = $request->file('file')->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $imported = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 1) continue;
            [$name, $order] = array_pad($row, 2, 0);

            TrackerColumn::updateOrCreate(
                ['name' => trim($name)],
                ['order' => (int) $order]
            );
            $imported++;
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
            if ($index === 0) continue;
            if (count($row) < 6) continue;
            [$date, $description, $reference, $accountCode, $debit, $credit] = $row;
            $grouped[$reference][] = [
                'date'         => trim($date),
                'description'  => trim($description),
                'account_code' => trim($accountCode),
                'debit'        => (float) $debit,
                'credit'       => (float) $credit,
            ];
        }

        $imported = 0;
        $skipped  = [];
        $errors   = [];

        foreach ($grouped as $reference => $lines) {
            $date        = $lines[0]['date'];
            $description = $lines[0]['description'];

            try {
                \Carbon\Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            } catch (\Exception $e) {
                $errors[] = "{$reference}: format tanggal tidak valid ({$date}), harus Y-m-d.";
                continue;
            }

            $hasInvalidRow = false;
            foreach ($lines as $line) {
                if (empty($line['account_code'])) {
                    $errors[] = "{$reference}: account_code kosong.";
                    $hasInvalidRow = true;
                    break;
                }
                if (!is_numeric($line['debit']) || !is_numeric($line['credit'])) {
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
            if ($hasInvalidRow) continue;

            $items = array_map(fn($l) => [
                'account_code' => $l['account_code'],
                'debit'        => $l['debit'],
                'credit'       => $l['credit'],
            ], $lines);

            try {
                $this->accountingService->createJournal($date, $description, $reference, $items);
                $imported++;
            } catch (\App\Exceptions\IdempotencyException $e) {
                $skipped[] = $reference;
            } catch (\App\Exceptions\BalanceMismatchException $e) {
                $errors[] = "{$reference}: debit dan kredit tidak balance.";
            } catch (\App\Exceptions\AccountNotFoundException $e) {
                $errors[] = "{$reference}: kode akun tidak ditemukan.";
            } catch (\Exception $e) {
                $errors[] = "{$reference}: " . $e->getMessage();
            }
        }

        $msg = "Imported: {$imported} jurnal.";
        if ($skipped) $msg .= ' Skipped (duplikat): ' . implode(', ', $skipped) . '.';
        if ($errors)  $msg .= ' Errors: ' . implode(' | ', $errors);

        return back()->with($errors ? 'error' : 'success', $msg);
    }
}
