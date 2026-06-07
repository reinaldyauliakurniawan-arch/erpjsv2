<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function exportJournals()
    {
        $journals = Journal::with('items.account')->orderBy('date')->get();
        return $this->streamCsv('journals_export.csv', ['date', 'description', 'reference', 'account_code', 'debit', 'credit'], function($file) use ($journals) {
            foreach ($journals as $journal) {
                foreach ($journal->items as $item) {
                    fputcsv($file, [
                        $journal->date,
                        $journal->description,
                        $journal->reference,
                        $item->account->code ?? '',
                        $item->debit,
                        $item->credit,
                    ]);
                }
            }
        });
    }

    public function exportAttendance()
    {
        $attendances = Attendance::with(['classSession.program', 'students.student.user'])->get();

        return $this->streamCsv('attendance_export.csv', ['ID', 'Date', 'Student', 'Program', 'Time Block'], function($file) use ($attendances) {
            foreach ($attendances as $attendance) {
                foreach ($attendance->students as $enrollment) {
                    fputcsv($file, [
                        $attendance->id,
                        $attendance->date,
                        $enrollment->student?->user?->name ?? '—',
                        $attendance->classSession?->program?->name ?? '—',
                        $attendance->time_block,
                    ]);
                }
            }
        });
    }

    public function exportPayroll()
    {
        $payroll = DB::table('attendance_tutor')
            ->join('tutors', 'attendance_tutor.tutor_id', '=', 'tutors.id')
            ->join('users', 'tutors.user_id', '=', 'users.id')
            ->select('users.name as tutor_name', 'attendance_tutor.payable_amount', 'attendance_tutor.paid_at', 'attendance_tutor.created_at')
            ->get();

        return $this->streamCsv('payroll_export.csv', ['Tutor', 'Amount', 'Paid At', 'Created At'], function($file) use ($payroll) {
            foreach ($payroll as $p) {
                fputcsv($file, [$p->tutor_name, $p->payable_amount, $p->paid_at, $p->created_at]);
            }
        });
    }

    public function exportTrialBalance(Request $request)
    {
    $from = $request->get('from');
    $to   = $request->get('to');

    $query = DB::table('journal_items')
        ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
        ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
        ->selectRaw('accounts.code, accounts.name, accounts.type, SUM(journal_items.debit) as debit, SUM(journal_items.credit) as credit')
        ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
        ->orderBy('accounts.code');

    if ($from) $query->whereDate('journals.date', '>=', $from);
    if ($to)   $query->whereDate('journals.date', '<=', $to);

    $rows = $query->get()->filter(fn($r) => $r->debit > 0 || $r->credit > 0)->map(function($r) {
        $normalDebet = ['Asset', 'Expense'];
        $debit  = (float) $r->debit;
        $credit = (float) $r->credit;
        if (in_array($r->type, $normalDebet)) {
            $saldo_debet  = $debit >= $credit ? $debit - $credit : 0;
            $saldo_kredit = $credit > $debit  ? $credit - $debit : 0;
        } else {
            $saldo_kredit = $credit >= $debit ? $credit - $debit : 0;
            $saldo_debet  = $debit > $credit  ? $debit - $credit : 0;
        }
        return [
            'code'         => $r->code,
            'name'         => $r->name,
            'type'         => $r->type,
            'debit'        => $debit,
            'credit'       => $credit,
            'saldo_debet'  => $saldo_debet,
            'saldo_kredit' => $saldo_kredit,
        ];
    });

    return $this->streamCsv('trial_balance.csv',
        ['Kode', 'Nama Akun', 'Tipe', 'Debit', 'Kredit', 'Saldo Debet', 'Saldo Kredit'],
        function($file) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($file, [
                    $row['code'], $row['name'], $row['type'],
                    $row['debit'], $row['credit'],
                    $row['saldo_debet'], $row['saldo_kredit'],
                ]);
            }
        }
    );
    }

    public function exportProfitLoss(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $accounts = Account::whereIn('type', ['Revenue', 'Expense'])->orderBy('code')->get()->keyBy('id');

        $totals = DB::table('journal_items')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->selectRaw('journal_items.account_id, SUM(journal_items.debit) as total_debit, SUM(journal_items.credit) as total_credit')
            ->whereBetween('journals.date', [$from, $to])
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = $accounts->map(function ($account) use ($totals) {
            $t      = $totals->get($account->id);
            $amount = $t ? ($account->type === 'Revenue' ? $t->total_credit : $t->total_debit) : 0;
            return ['code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'amount' => $amount];
        })->filter(fn($r) => $r['amount'] > 0);

        $filename = "profit_loss_{$from}_{$to}.csv";
        return $this->streamCsv($filename, ['Kode', 'Nama Akun', 'Tipe', 'Amount'], function($file) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($file, [$row['code'], $row['name'], $row['type'], $row['amount']]);
            }
        });
    }

    public function exportBalanceSheet(Request $request)
    {
        $asOf = $request->get('as_of', now()->toDateString());

        $accounts = Account::whereIn('type', ['Asset', 'Liability', 'Equity'])->orderBy('code')->get()->keyBy('id');

        $totals = DB::table('journal_items')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->selectRaw('journal_items.account_id, SUM(journal_items.debit) as total_debit, SUM(journal_items.credit) as total_credit')
            ->whereDate('journals.date', '<=', $asOf)
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = $accounts->map(function ($account) use ($totals) {
            $t       = $totals->get($account->id);
            $debit   = $t ? $t->total_debit  : 0;
            $credit  = $t ? $t->total_credit : 0;
            $balance = $account->type === 'Asset' ? ($debit - $credit) : ($credit - $debit);
            return ['code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'balance' => $balance];
        })->filter(fn($r) => $r['balance'] != 0);

        $filename = "balance_sheet_{$asOf}.csv";
        return $this->streamCsv($filename, ['Kode', 'Nama Akun', 'Tipe', 'Balance'], function($file) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($file, [$row['code'], $row['name'], $row['type'], $row['balance']]);
            }
        });
    }

    public function exportCoA()
    {
        $accounts = Account::orderBy('code')->get();

        return $this->streamCsv('chart_of_accounts.csv', ['code', 'name', 'type', 'cash_flow_category'], function($file) use ($accounts) {
            foreach ($accounts as $account) {
                fputcsv($file, [$account->code, $account->name, $account->type, $account->cash_flow_category ?? '']);
            }
        });
    }

    public function downloadTemplate(string $type)
    {
        $templates = [
            'coa' => [
                'filename' => 'template_coa.csv',
                'headers'  => ['code', 'name', 'type', 'cash_flow_category'],
                'example'  => [['1001', 'Kas di Tangan', 'Asset', 'cash']],
            ],
            'classrooms' => [
                'filename' => 'template_classrooms.csv',
                'headers'  => ['name', 'capacity'],
                'example'  => [['Ruang A', '10']],
            ],
            'programs' => [
                'filename' => 'template_programs.csv',
                'headers'  => ['name', 'type', 'price', 'total_meetings', 'min_quota'],
                'example'  => [['ESP Flash', 'group', '1500000', '12', '3']],
            ],
            'tutors' => [
                'filename' => 'template_tutors.csv',
                'headers'  => ['name', 'email', 'persona', 'program_name', 'rate'],
                'example'  => [
                    ['John Doe', 'john@example.com', 'S1', 'ESP Flash', '55000'],
                    ['John Doe', 'john@example.com', 'S1', 'ESP Plus', '55000'],
                    ['Jane Smith', 'jane@example.com', 'S2', 'ESP Flash', '60000'],
                ],
            ],
            'students' => [
                'filename' => 'template_students.csv',
                'headers'  => ['name', 'email', 'notes'],
                'example'  => [['Budi Santoso', 'budi@example.com', 'beginner']],
            ],
            'enrollments' => [
                'filename' => 'template_enrollments.csv',
                'headers'  => ['student_email', 'program_name', 'class_session_name', 'enrollment_date', 'expiry_date', 'payment_method', 'payment_channel', 'total_amount', 'payment_status', 'status', 'remaining_meetings'],
                'example'  => [['budi@example.com', 'ESP Flash', 'ESP Flash Batch 1', '2024-01-01', '2024-06-01', 'full upfront', 'cash', '1500000', 'full', 'active', '12']],
            ],
            'installments' => [
                'filename' => 'template_installments.csv',
                'headers'  => ['student_email', 'program_name', 'amount', 'due_date', 'paid_at', 'payment_channel'],
                'example'  => [['budi@example.com', 'ESP Flash', '750000', '2024-01-01', '2024-01-01', 'cash']],
            ],
            'schedules' => [
                'filename' => 'template_schedules.csv',
                'headers'  => ['student_email', 'program_name', 'classroom_name', 'day', 'time_block', 'class_session_name'],
                'example'  => [['budi@example.com', 'ESP Flash', 'Room A', 'Monday', '09.00-10.30', '']],
            ],
            'tutor_availability' => [
                'filename' => 'template_tutor_availability.csv',
                'headers'  => ['tutor_email', 'day', 'time_block', 'status'],
                'example'  => [['john@example.com', 'Monday', '09.00-10.30', 'available']],
            ],
            'class_sessions' => [
                'filename' => 'template_class_sessions.csv',
                'headers'  => ['name', 'program_name', 'class_type', 'status'],
                'example'  => [['ESP Flash Batch 1', 'ESP Flash', 'group', 'active']],
            ],
            'rabs' => [
                'filename' => 'template_rabs.csv',
                'headers'  => ['year', 'division', 'account_name', 'account_code', 'activity', 'q1', 'q2', 'q3', 'q4'],
                'example'  => [['2024', 'Akademik', 'Biaya Operasional', '6001', 'Pelatihan', '1000000', '1000000', '1000000', '1000000']],
            ],
            'fixed_assets' => [
                'filename' => 'template_fixed_assets.csv',
                'headers'  => ['name', 'category', 'acquired_at', 'cost', 'salvage_value', 'useful_life', 'depreciation_method', 'notes', 'expense_account_code', 'accumulated_account_code', 'is_active'],
                'example'  => [['Laptop Dell', 'Electronics', '2024-01-01', '15000000', '1000000', '4', 'straight_line', '', '6001', '1601', '1']],
            ],
            'tracker_columns' => [
                'filename' => 'template_tracker_columns.csv',
                'headers'  => ['name', 'order'],
                'example'  => [['Speaking', '1']],
            ],
            'journals' => [
                'filename' => 'template_journals.csv',
                'headers'  => ['date', 'description', 'reference', 'account_code', 'debit', 'credit'],
                'example'  => [
                    ['2024-01-01', 'Pembayaran siswa', 'TRX-001', '1001', '500000', '0'],
                    ['2024-01-01', 'Pembayaran siswa', 'TRX-001', '4001', '0', '500000'],
                ],
            ],
        ];

        if (!array_key_exists($type, $templates)) {
            abort(404);
        }

        $tpl = $templates[$type];

        return $this->streamCsv($tpl['filename'], $tpl['headers'], function($file) use ($tpl) {
            foreach ($tpl['example'] as $row) {
                fputcsv($file, $row);
            }
        });
    }
    public function exportDeferredRevenue(Request $request)
{
    $filterMonth   = $request->get('month');
    $filterProgram = $request->get('program');

    $query = DB::table('enrollments')
        ->join('students', 'enrollments.student_id', '=', 'students.id')
        ->join('users', 'students.user_id', '=', 'users.id')
        ->join('programs', 'enrollments.program_id', '=', 'programs.id')
        ->leftJoin(
            DB::raw('(SELECT enrollment_id, SUM(amount) as paid_amount FROM installments WHERE paid_at IS NOT NULL GROUP BY enrollment_id) as paid'),
            'paid.enrollment_id', '=', 'enrollments.id'
        )
        ->where('enrollments.status', 'active')
        ->select(
            'users.name as student_name',
            'programs.name as program_name',
            'enrollments.remaining_meetings',
            'enrollments.total_amount',
            'enrollments.payment_method',
            'enrollments.created_at',
            'programs.total_meetings',
            DB::raw('COALESCE(paid.paid_amount, 0) as paid_amount')
        );

    if ($filterProgram) $query->where('programs.id', $filterProgram);
    if ($filterMonth)   $query->whereRaw("DATE_FORMAT(enrollments.created_at, '%Y-%m') = ?", [$filterMonth]);

    $rows = $query->get()->map(function ($e) {
        $paidAmount = $e->payment_method === 'full upfront'
            ? (float) $e->total_amount
            : (float) $e->paid_amount;
        if ($e->total_meetings <= 0 || $paidAmount <= 0) return null;
        $rate       = $paidAmount / $e->total_meetings;
        $used       = $e->total_meetings - $e->remaining_meetings;
        $recognized = $rate * $used;
        $deferred   = $paidAmount - $recognized;
        return [
            $e->student_name,
            $e->program_name,
            substr($e->created_at, 0, 7),
            $e->total_meetings,
            $used,
            $e->remaining_meetings,
            $paidAmount,
            round($recognized),
            round($deferred),
        ];
    })->filter();

    return $this->streamCsv(
        'deferred_revenue.csv',
        ['Siswa', 'Program', 'Bulan', 'Total Sesi', 'Terpakai', 'Sisa', 'Harga Dibayar', 'Sudah Diakui', 'Sisa Deferred'],
        function ($file) use ($rows) {
            foreach ($rows as $row) fputcsv($file, $row);
        }
    );
}

    protected function streamCsv($filename, $headers, $callback)
    {
        $resHeaders = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0'
        ];

        return response()->stream(function() use ($headers, $callback) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            $callback($file);
            fclose($file);
        }, 200, $resHeaders);
    }
}
