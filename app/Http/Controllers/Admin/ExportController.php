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
        $journals = Journal::with('items.account')->get();
        return $this->streamCsv('journals_export.csv', ['ID', 'Date', 'Reference', 'Description', 'Amount'], function($file) use ($journals) {
            foreach ($journals as $journal) {
                fputcsv($file, [$journal->id, $journal->date, $journal->reference, $journal->description, $journal->total_amount]);
            }
        });
    }

    public function exportAttendance()
    {
        $attendances = Attendance::with(['enrollment.student.user', 'enrollment.program'])->get();
        return $this->streamCsv('attendance_export.csv', ['ID', 'Date', 'Student', 'Program', 'Time Block'], function($file) use ($attendances) {
            foreach ($attendances as $attendance) {
                fputcsv($file, [
                    $attendance->id,
                    $attendance->date,
                    $attendance->enrollment->student->user->name,
                    $attendance->enrollment->program->name,
                    $attendance->time_block
                ]);
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

    public function exportTrialBalance()
    {
        $accounts = Account::orderBy('code')->get()->keyBy('id');

        $totals = DB::table('journal_items')
            ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $rows = $accounts->map(function ($account) use ($totals) {
            $t      = $totals->get($account->id);
            $debit  = $t ? $t->total_debit  : 0;
            $credit = $t ? $t->total_credit : 0;
            return ['code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'debit' => $debit, 'credit' => $credit];
        })->filter(fn($r) => $r['debit'] > 0 || $r['credit'] > 0);

        return $this->streamCsv('trial_balance.csv', ['Kode', 'Nama Akun', 'Tipe', 'Debit', 'Credit'], function($file) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($file, [$row['code'], $row['name'], $row['type'], $row['debit'], $row['credit']]);
            }
        });
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

        return $this->streamCsv('chart_of_accounts.csv', ['Kode', 'Nama Akun', 'Tipe'], function($file) use ($accounts) {
            foreach ($accounts as $account) {
                fputcsv($file, [$account->code, $account->name, $account->type]);
            }
        });
    }

    public function downloadTemplate(string $type)
    {
        $templates = [
            'coa' => [
                'filename' => 'template_coa.csv',
                'headers'  => ['code', 'name', 'type'],
                'example'  => [['1001', 'Kas di Tangan', 'Asset']],
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
