<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $parties = Party::query()->get(['id', 'opening_balance', 'opening_balance_side']);
        $partyLedgerBalances = Ledger::query()
            ->whereNotNull('party_id')
            ->selectRaw('party_id, COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->groupBy('party_id')
            ->pluck('balance', 'party_id');

        $partyBalances = $parties->map(function (Party $party) use ($partyLedgerBalances) {
            $ledgerBalance = (float) ($partyLedgerBalances[$party->id] ?? 0);
            $opening = (float) ($party->opening_balance ?? 0);
            $openingSigned = ($party->opening_balance_side ?? 'dr') === 'cr' ? -$opening : $opening;

            return $ledgerBalance + $openingSigned;
        });

        $accounts = Account::query()
            ->orderBy('name')
            ->get();

        $accountLedgerBalances = Ledger::query()
            ->whereNotNull('account_id')
            ->selectRaw('account_id, COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->groupBy('account_id')
            ->pluck('balance', 'account_id');

        $accounts->each(function (Account $account) use ($accountLedgerBalances): void {
            $ledgerBalance = (float) ($accountLedgerBalances[$account->id] ?? 0);
            $opening = (float) ($account->opening_balance ?? 0);
            $openingSigned = ($account->opening_balance_side ?? 'dr') === 'cr' ? -$opening : $opening;

            $account->balance = $ledgerBalance + $openingSigned;
        });

        return view('dashboard', [
            'totalReceivable' => (float) $partyBalances->filter(fn ($balance) => $balance > 0)->sum(),
            'totalPayable' => abs((float) $partyBalances->filter(fn ($balance) => $balance < 0)->sum()),
            'accounts' => $accounts,
            'recentSales' => Sale::query()->with('party')->latest()->limit(5)->get(),
            'recentPurchases' => Purchase::query()->with('party')->latest()->limit(5)->get(),
            'recentPayments' => Payment::query()->with(['party', 'account'])->latest()->limit(5)->get(),
        ]);
    }
}
