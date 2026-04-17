<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\LedgerService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        $parties = Party::query()
            ->whereDoesntHave('employees')
            ->get(['id']);
        $partyLedgerBalances = Ledger::query()
            ->whereNotNull('party_id')
            ->selectRaw('party_id, COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->groupBy('party_id')
            ->pluck('balance', 'party_id');

        $partyBalances = $parties->map(function (Party $party) use ($partyLedgerBalances) {
            return (float) ($partyLedgerBalances[$party->id] ?? 0);
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
            $account->balance = (float) ($accountLedgerBalances[$account->id] ?? 0);
        });

        return view('dashboard', [
            'totalReceivable' => (float) $partyBalances->filter(fn ($balance) => $balance > 0)->sum(),
            'totalPayable' => abs((float) $partyBalances->filter(fn ($balance) => $balance < 0)->sum()),
            'accounts' => $accounts,
            'recentSales' => Sale::query()->with('party')->where('status', Sale::STATUS_ACTIVE)->orderByDesc('date')->orderByDesc('id')->limit(5)->get(),
            'recentPurchases' => Purchase::query()->with('party')->where('status', Purchase::STATUS_ACTIVE)->orderByDesc('date')->orderByDesc('id')->limit(5)->get(),
            'recentPayments' => Payment::query()->with(['party', 'account'])->orderByDesc('date')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
