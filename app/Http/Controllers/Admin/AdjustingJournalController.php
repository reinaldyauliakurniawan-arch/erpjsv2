<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdjustingJournal;
use App\Models\AdjustingJournalItem;
use App\Models\Account;
use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdjustingJournalController extends Controller
{
    public function index()
    {
        $stats = [
            'draft'  => AdjustingJournal::where('status', 'draft')->count(),
            'posted' => AdjustingJournal::where('status', 'posted')->count(),
        ];
        $accounts = Account::orderBy('code')->get();
        return view('admin.adjusting-journals.index', compact('stats', 'accounts'));
    }

    public function data(Request $request)
    {
        $q = AdjustingJournal::with(['items.account'])
            ->when($request->type,   fn($q, $v) => $q->where('type', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->period_from, fn($q, $v) => $q->where('period', '>=', $v))
            ->when($request->period_to,   fn($q, $v) => $q->where('period', '<=', $v))
            ->orderBy('period', 'desc')
            ->orderBy('reference');

        $page = max(1, (int) $request->get('page', 1));
        $size = (int) $request->get('size', 20);
        $paginated = $q->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'data'      => $paginated->map(fn($aj) => [
                'id'          => $aj->id,
                'period'      => $aj->period->format('d M Y'),
                'reference'   => $aj->reference,
                'description' => $aj->description,
                'type'        => $aj->type,
                'type_label'  => match($aj->type) {
                    'depreciation'    => 'Depresiasi',
                    'amortization'    => 'Amortisasi',
                    'deferred_revenue'=> 'Deferred Revenue',
                    default           => 'Manual',
                },
                'debit'        => $aj->items->sum('debit'),
                'credit'       => $aj->items->sum('credit'),
                'status'       => $aj->status,
                'posted_journal_id' => $aj->posted_journal_id,
                'show_url'     => $aj->posted_journal_id
                    ? route('finance.journals.show', $aj->posted_journal_id)
                    : null,
                'items'        => $aj->items->map(fn($i) => [
                    'account_code' => $i->account->code ?? '',
                    'account_name' => $i->account->name ?? '',
                    'debit'        => $i->debit,
                    'credit'       => $i->credit,
                ]),
            ]),
            'last_page' => $paginated->lastPage(),
            'total'     => $paginated->total(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'period'      => 'required|date',
            'description' => 'required|string',
            'items'       => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.debit'      => 'required|numeric|min:0',
            'items.*.credit'     => 'required|numeric|min:0',
        ]);

        $totalDebit  = collect($request->items)->sum('debit');
        $totalCredit = collect($request->items)->sum('credit');

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withErrors(['items' => 'Total debit harus sama dengan total kredit.']);
        }

        DB::transaction(function () use ($request) {
            $period = Carbon::parse($request->period)->endOfMonth()->toDateString();

            $aj = AdjustingJournal::create([
                'period'      => $period,
                'reference'   => AdjustingJournal::generateReference($period),
                'description' => $request->description,
                'type'        => 'manual',
                'status'      => 'draft',
                'total_amount'=> collect($request->items)->sum('debit'),
            ]);

            foreach ($request->items as $item) {
                AdjustingJournalItem::create([
                    'adjusting_journal_id' => $aj->id,
                    'account_id'           => $item['account_id'],
                    'debit'                => $item['debit'],
                    'credit'               => $item['credit'],
                ]);
            }

            // Langsung post ke journals
            if (Journal::where('reference', $aj->reference)->exists()) {
                throw new \App\Exceptions\IdempotencyException("Journal dengan reference {$aj->reference} sudah ada.");
            }

            $journal = Journal::create([
                'date'         => $aj->period,
                'description'  => "[AJP] {$aj->description}",
                'reference'    => $aj->reference,
                'total_amount' => $aj->total_amount,
                'type'         => 'adjusting',
            ]);

            foreach ($aj->fresh()->items as $item) {
                $journal->items()->create([
                    'account_id' => $item->account_id,
                    'debit'      => $item->debit,
                    'credit'     => $item->credit,
                ]);
            }

            $aj->update(['status' => 'posted', 'posted_journal_id' => $journal->id]);
        });

        return back()->with('success', 'Jurnal penyesuaian manual berhasil disimpan dan diposting.');
    }

    public function generate(Request $request)
    {
        $request->validate(['period' => 'required|date']);
        $period = Carbon::parse($request->period)->format('Y-m-d');

        \Artisan::call('finance:generate-ajp', ['--period' => $period]);

        return back()->with('success', "Generate AJP untuk periode {$period} berhasil.");
    }

    public function destroy(AdjustingJournal $adjustingJournal)
    {
        if ($adjustingJournal->status === 'posted') {
            return response()->json(['error' => 'Jurnal yang sudah diposting tidak dapat dihapus.'], 422);
        }
        $adjustingJournal->delete();
        return response()->json(['success' => true]);
    }
}
