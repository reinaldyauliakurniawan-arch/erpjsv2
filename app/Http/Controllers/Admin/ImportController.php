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

            // Validasi format date
            try {
                \Carbon\Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            } catch (\Exception $e) {
                $errors[] = "{$reference}: format tanggal tidak valid ({$date}), harus Y-m-d.";
                continue;
            }

            // Validasi setiap baris: account_code tidak boleh kosong, debit/credit harus numerik
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
