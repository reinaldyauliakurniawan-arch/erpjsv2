<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Account;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function __construct(protected AccountingService $accountingService) {}

    public function index()
    {
        $journals = Journal::with('items.account')->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(20);
        return view('admin.journals.index', compact('journals'));
    }

    public function data(Request $request)
{
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

    $page     = max(1, (int) $request->input('page', 1));
    $size     = (int) $request->input('size', 20);
    $total    = $query->count();
    $journals = $query->skip(($page - 1) * $size)->take($size)->get();

    return response()->json([
        'last_page' => ceil($total / $size),
        'data'      => $journals->map(fn($j) => [
            'id'           => $j->id,
            'date'         => \Carbon\Carbon::parse($j->date)->format('d M Y'),
            'reference'    => $j->reference,
            'description'  => $j->description,
            'total_amount' => $j->total_amount,
            'show_url'     => route('finance.journals.show', $j),
        ]),
    ]);
}

    public function create()
    {
        $accounts = Account::orderBy('code')->get();
        return view('admin.journals.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'date'        => 'required|date',
            'description' => 'required|string',
            'reference'   => 'required|string|unique:journals,reference',
            'items'       => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.debit'      => 'required|integer|min:0',
            'items.*.credit'     => 'required|integer|min:0',
        ]);

        $items = collect($request->items)->filter(fn($i) => $i['debit'] > 0 || $i['credit'] > 0);

        $totalDebit  = $items->sum('debit');
        $totalCredit = $items->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.001) {
            return back()->withInput()->with('error', "Total debit (Rp " . number_format($totalDebit) . ") tidak sama dengan total credit (Rp " . number_format($totalCredit) . ").");
        }

        // Map account_id ke account_code untuk AccountingService
        $accountMap = Account::whereIn('id', $items->pluck('account_id'))->pluck('code', 'id');

        $journalItems = $items->map(fn($i) => [
            'account_code' => $accountMap[$i['account_id']],
            'debit'        => (float) $i['debit'],
            'credit'       => (float) $i['credit'],
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
        $journal->load('items.account');
        $reverseRef = 'REV-' . $journal->reference;
        $alreadyReversed = Journal::where('reference', $reverseRef)->exists();
        return view('admin.journals.show', compact('journal', 'alreadyReversed'));
    }

    public function reverse(Journal $journal)
    {
        $reverseRef = 'REV-' . $journal->reference;

        if (Journal::where('reference', $reverseRef)->exists()) {
            return back()->with('error', 'Jurnal ini sudah pernah di-reverse.');
        }

        $journal->load('items.account');

        $items = $journal->items->map(fn($item) => [
            'account_code' => $item->account->code,
            'debit'        => $item->credit,
            'credit'       => $item->debit,
        ])->toArray();

        try {
            $this->accountingService->createJournal(
                now()->toDateString(),
                'REVERSE: ' . $journal->description,
                $reverseRef,
                $items
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.journals.index')->with('success', 'Jurnal berhasil di-reverse.');
    }
}
