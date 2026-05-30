<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::orderBy('code')->get()->groupBy('type');
        return view('admin.accounts.index', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'               => 'required|string|unique:accounts,code',
            'name'               => 'required|string',
            'type'               => 'required|in:Asset,Liability,Equity,Revenue,Expense',
            'cash_flow_category' => 'nullable|in:cash,operating,investing,financing',
        ]);
        Account::create($request->only('code', 'name', 'type', 'cash_flow_category'));
        return back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(Request $request, Account $account)
    {
        $request->validate([
            'code'               => 'required|string|unique:accounts,code,' . $account->id,
            'name'               => 'required|string',
            'type'               => 'required|in:Asset,Liability,Equity,Revenue,Expense',
            'cash_flow_category' => 'nullable|in:cash,operating,investing,financing',
        ]);
        $account->update($request->only('code', 'name', 'type', 'cash_flow_category'));
        return back()->with('success', 'Akun berhasil diupdate.');
    }

    public function destroy(Account $account)
    {
        $hasTransactions = DB::table('journal_items')
            ->where('account_id', $account->id)
            ->exists();
        if ($hasTransactions) {
            return back()->with('error', 'Akun tidak bisa dihapus karena sudah memiliki transaksi.');
        }
        $account->delete();
        return back()->with('success', 'Akun berhasil dihapus.');
    }
}
