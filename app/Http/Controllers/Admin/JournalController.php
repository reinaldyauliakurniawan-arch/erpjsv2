<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Journal;
use App\Services\AccountingService;
use App\Services\AttendanceService;
use App\Services\EnrollmentLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected AttendanceService $attendanceService,
        protected EnrollmentLedgerService $ledgerService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Journal::class);

        $journals = Journal::with('items.account')->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(20);

        return view('admin.journals.index', compact('journals'));
    }

    public function data(Request $request)
    {
        $this->authorize('viewAny', Journal::class);

        $query = Journal::with('items')->orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $page = max(1, (int) $request->input('page', 1));
        $size = (int) $request->input('size', 20);
        $total = $query->count();
        $journals = $query->skip(($page - 1) * $size)->take($size)->get();

        return response()->json([
            'last_page' => ceil($total / $size),
            'data' => $journals->map(fn ($j) => [
                'id' => $j->id,
                'date' => Carbon::parse($j->date)->format('d M Y'),
                'reference' => $j->reference,
                'description' => $j->description,
                'total_amount' => $j->total_amount,
                'show_url' => route('finance.journals.show', $j),
            ]),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Journal::class);

        $accounts = Account::orderBy('code')->get();

        return view('admin.journals.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Journal::class);

        $request->validate([
            'date' => 'required|date',
            'description' => 'required|string',
            'reference' => 'required|string|unique:journals,reference',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.debit' => 'required|integer|min:0',
            'items.*.credit' => 'required|integer|min:0',
        ]);

        $items = collect($request->items)->filter(fn ($i) => $i['debit'] > 0 || $i['credit'] > 0);

        $totalDebit = $items->sum('debit');
        $totalCredit = $items->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.001) {
            return back()->withInput()->with('error', 'Total debit (Rp '.number_format($totalDebit).') tidak sama dengan total credit (Rp '.number_format($totalCredit).').');
        }

        // Map account_id ke account_code untuk AccountingService
        $accountMap = Account::whereIn('id', $items->pluck('account_id'))->pluck('code', 'id');

        $journalItems = $items->map(fn ($i) => [
            'account_code' => $accountMap[$i['account_id']],
            'debit' => (float) $i['debit'],
            'credit' => (float) $i['credit'],
        ])->values()->toArray();

        try {
            $this->accountingService->createJournal(
                $request->date,
                $request->description,
                $request->reference,
                $journalItems
            );
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.journals.index')->with('success', 'Jurnal berhasil disimpan.');
    }

    public function show(Journal $journal)
    {
        $this->authorize('view', $journal);

        $journal->load('items.account');
        $reverseRef = 'REV-'.$journal->reference;
        $alreadyReversed = Journal::where('reference', $reverseRef)->exists();

        return view('admin.journals.show', compact('journal', 'alreadyReversed'));
    }

    public function reverse(Journal $journal)
    {
        $this->authorize('update', $journal);

        $ref = $journal->reference;
        $reverseRef = 'REV-'.$ref;

        // "REV-<ref>" = jurnal pembalik. Kecuali "REV-REC-*" yang justru jurnal
        // pengakuan pendapatan (revenue recognition), bukan pembalik.
        if (str_starts_with($ref, 'REV-') && ! str_starts_with($ref, 'REV-REC-')) {
            return back()->with('error', 'Ini sudah jurnal pembalik.');
        }
        if (Journal::where('reference', $reverseRef)->exists()) {
            return back()->with('error', 'Jurnal ini sudah pernah di-reverse.');
        }

        // Jurnal yang lahir dari operasi lain: pembalikannya HARUS lewat operasi
        // asalnya, supaya sisa pertemuan siswa, status cicilan, honor tutor, dan
        // jam tutor ikut kembali — bukan cuma angka buku besar.
        try {
            // Pengakuan pendapatan / akru honor tutor per pertemuan → batalkan
            // seluruh absensi pertemuan itu (pendapatan + honor + sisa pertemuan).
            if (preg_match('/^(?:REV-REC|TUTOR-PAY)-(\d+)-\d+$/', $ref, $m)) {
                $attendance = Attendance::find((int) $m[1]);
                if (! $attendance) {
                    return back()->with('error', 'Absensi sumber jurnal ini sudah tidak ada.');
                }
                $this->attendanceService->reverseAttendance($attendance);

                return redirect()->route('finance.journals.index')->with(
                    'success',
                    'Absensi pertemuan dibatalkan: pendapatan, honor tutor, dan sisa pertemuan siswa ikut dikembalikan.'
                );
            }

            // Tarif honor yang di-assign CFO → kembalikan ke "menunggu tarif".
            if (preg_match('/^RATE-AT-(\d+)$/', $ref, $m)) {
                return $this->reverseAssignedRate((int) $m[1], $journal);
            }

            // Cicilan yang ditandai lunas → tandai belum lunas + bangun ulang buku besar.
            if (preg_match('/^INSTALLMENT-(\d+)$/', $ref, $m)) {
                return $this->reverseInstallmentPayment((int) $m[1]);
            }

            // Jurnal tanpa pemetaan operasional 1:1 → arahkan ke layar yang benar.
            if (str_starts_with($ref, 'PAYMENT-ENROLL-') || str_starts_with($ref, 'MANUAL-EXPIRY-') || str_starts_with($ref, 'ENR-SYNC-')) {
                return back()->with('error', 'Jurnal ini terkait data enrollment. Koreksi lewat halaman Edit Enrollment — buku besar otomatis dibangun ulang mengikuti data baru.');
            }
            if (str_starts_with($ref, 'PAYROLL-')) {
                return back()->with('error', 'Jurnal ini bagian dari payroll. Batalkan lewat tombol "Reverse" di halaman Payroll supaya status pembayaran tutor ikut kembali.');
            }
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Jurnal manual / umum / migrasi / penyesuaian: pembalikan biasa.
        $journal->load('items.account');

        $items = $journal->items->map(fn ($item) => [
            'account_code' => $item->account->code,
            'debit' => $item->credit,
            'credit' => $item->debit,
        ])->toArray();

        try {
            $this->accountingService->createJournal(
                now()->toDateString(),
                'REVERSE: '.$journal->description,
                $reverseRef,
                $items
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.journals.index')->with('success', 'Jurnal berhasil di-reverse.');
    }

    /**
     * Batalkan tarif honor yang sudah di-assign CFO untuk satu baris presensi
     * tutor. Jurnal akru-nya dibalik, dan barisnya kembali ke "menunggu tarif".
     */
    private function reverseAssignedRate(int $attendanceTutorId, Journal $journal)
    {
        return DB::transaction(function () use ($attendanceTutorId, $journal) {
            $row = DB::table('attendance_tutor')->where('id', $attendanceTutorId)->lockForUpdate()->first();
            if (! $row) {
                return back()->with('error', 'Data honor sumber jurnal ini sudah tidak ada.');
            }
            if ($row->paid_at) {
                return back()->with('error', 'Honor ini sudah dibayar lewat payroll — tidak bisa dibatalkan dari sini.');
            }

            $journal->load('items.account');
            $this->accountingService->createJournal(
                now()->toDateString(),
                'REVERSE: '.$journal->description,
                'REV-'.$journal->reference,
                $journal->items->map(fn ($i) => [
                    'account_code' => $i->account->code,
                    'debit' => $i->credit,
                    'credit' => $i->debit,
                ])->toArray(),
            );

            DB::table('attendance_tutor')->where('id', $attendanceTutorId)->update([
                'pending_rate' => true,
                'payable_amount' => 0,
                'journal_id' => null,
            ]);

            return redirect()->route('finance.journals.index')
                ->with('success', 'Tarif honor dibatalkan — presensi kembali ke status "menunggu tarif".');
        });
    }

    /**
     * Batalkan status lunas satu cicilan. Buku besar enrollment dibangun ulang
     * dari data operasional baru (kas turun; kalau uangnya sempat menutup
     * piutang, piutangnya muncul lagi). payment_status ikut diselaraskan.
     */
    private function reverseInstallmentPayment(int $installmentId)
    {
        return DB::transaction(function () use ($installmentId) {
            $installment = Installment::lockForUpdate()->find($installmentId);
            if (! $installment) {
                return back()->with('error', 'Cicilan sumber jurnal ini sudah tidak ada.');
            }
            if (! $installment->paid_at) {
                return back()->with('error', 'Cicilan ini memang belum ditandai lunas.');
            }

            $enrollment = Enrollment::with('program')->lockForUpdate()->find($installment->enrollment_id);
            if (! $enrollment) {
                return back()->with('error', 'Enrollment sumber jurnal ini sudah tidak ada.');
            }
            Installment::where('enrollment_id', $enrollment->id)->lockForUpdate()->get();

            $installment->update(['paid_at' => null]);

            $enrollment->refresh()->load('program');
            $this->ledgerService->rebuild($enrollment, optional($enrollment->enrollment_date)->toDateString());

            $unpaid = $enrollment->installments()->whereNull('paid_at')->count();
            $enrollment->update([
                'payment_status' => $unpaid === 0 ? PaymentStatus::FULL->value : PaymentStatus::PARTIAL->value,
            ]);

            return redirect()->route('finance.journals.index')
                ->with('success', 'Pembayaran cicilan dibatalkan & buku besar enrollment dibangun ulang.');
        });
    }
}
