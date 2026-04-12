<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReportController extends Controller
{
    public function cashbook(Request $request): View
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        try {
            [$fromAd, $toAd] = DateHelper::getAdRangeFromBsFilters($filters['from_date_bs'] ?? null, $filters['to_date_bs'] ?? null);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'from_date_bs' => $exception->getMessage(),
                'to_date_bs' => $exception->getMessage(),
            ]);
        }

        $cashAccounts = Account::query()
            ->where('type', 'cash')
            ->orderBy('name')
            ->get();

        $selectedAccountId = $filters['account_id'] ?? null;
        $hasSearched = filled($selectedAccountId)
            || filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null);

        $query = Ledger::query()
            ->whereNotNull('account_id')
            ->whereIn('account_id', $cashAccounts->pluck('id'));

        if ($selectedAccountId) {
            $query->where('account_id', $selectedAccountId);
        }

        if ($hasSearched) {
            $openingBase = $this->openingBalanceBase($cashAccounts, $selectedAccountId);

            $openingBalance = $openingBase + ((clone $query)
                ->when($fromAd, fn ($builder) => $builder->whereDate('created_at', '<', $fromAd))
                ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
                ->value('balance') ?? 0);

            $ledgerRows = (clone $query)
                ->when($fromAd, fn ($builder) => $builder->whereDate('created_at', '>=', $fromAd))
                ->when($toAd, fn ($builder) => $builder->whereDate('created_at', '<=', $toAd))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        } else {
            $openingBalance = 0;
            $ledgerRows = collect();
        }

        $paymentMap = Payment::query()
            ->with('party:id,name')
            ->whereIn('id', $ledgerRows->where('ref_table', 'payments')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($ledgerRows as $row) {
            $payment = $paymentMap->get($row->ref_id);
            $row->reference_text = $payment
                ? 'Payment / ' . ($payment->party?->name ?? 'Unknown Party')
                : (ucfirst($row->ref_table) . ' / ' . $row->ref_id);
        }

        $periodDebit = (float) $ledgerRows->sum(fn (Ledger $row) => (float) $row->dr_amount);
        $periodCredit = (float) $ledgerRows->sum(fn (Ledger $row) => (float) $row->cr_amount);

        return view('reports.cashbook', [
            'cashAccounts' => $cashAccounts,
            'ledgerRows' => $ledgerRows,
            'openingBalance' => (float) $openingBalance,
            'periodDebit' => $periodDebit,
            'periodCredit' => $periodCredit,
            'filters' => [
                'account_id' => $selectedAccountId,
                'from_date_bs' => $filters['from_date_bs'] ?? null,
                'to_date_bs' => $filters['to_date_bs'] ?? null,
            ],
            'hasSearched' => $hasSearched,
        ]);
    }

    public function profitLoss(Request $request): View
    {
        $todayBs = DateHelper::getCurrentBS();
        $startBs = substr($todayBs, 0, 8) . '01';

        $filters = $request->validate([
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        $hasSearched = filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null);

        $fromBs = $filters['from_date_bs'] ?? $startBs;
        $toBs = $filters['to_date_bs'] ?? $todayBs;

        if ($hasSearched) {
            try {
                [$fromAd, $toAd] = DateHelper::getAdRangeFromBsFilters($fromBs, $toBs);
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'from_date_bs' => $exception->getMessage(),
                    'to_date_bs' => $exception->getMessage(),
                ]);
            }

            $salesTotal = (float) Ledger::query()
                ->where('type', 'sale')
                ->whereDate('created_at', '>=', $fromAd)
                ->whereDate('created_at', '<=', $toAd)
                ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as total')
                ->value('total');

            $purchaseTotal = (float) Ledger::query()
                ->where('type', 'purchase')
                ->whereDate('created_at', '>=', $fromAd)
                ->whereDate('created_at', '<=', $toAd)
                ->selectRaw('COALESCE(SUM(cr_amount) - SUM(dr_amount), 0) as total')
                ->value('total');

            $profitLoss = $salesTotal - $purchaseTotal;

            $salesDetails = Sale::query()
                ->withTrashed()
                ->with('party:id,name')
                ->whereDate('created_at', '>=', $fromAd)
                ->whereDate('created_at', '<=', $toAd)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $purchaseDetails = Purchase::query()
                ->withTrashed()
                ->with('party:id,name')
                ->whereDate('created_at', '>=', $fromAd)
                ->whereDate('created_at', '<=', $toAd)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $salesDetails->each(fn (Sale $sale) => $sale->created_at_bs = DateHelper::adToBs($sale->created_at));
            $purchaseDetails->each(fn (Purchase $purchase) => $purchase->created_at_bs = DateHelper::adToBs($purchase->created_at));
        } else {
            $salesTotal = 0;
            $purchaseTotal = 0;
            $profitLoss = 0;
            $salesDetails = collect();
            $purchaseDetails = collect();
        }

        return view('reports.profit-loss', [
            'filters' => [
                'from_date_bs' => $fromBs,
                'to_date_bs' => $toBs,
            ],
            'salesTotal' => $salesTotal,
            'purchaseTotal' => $purchaseTotal,
            'profitLoss' => $profitLoss,
            'salesDetails' => $salesDetails,
            'purchaseDetails' => $purchaseDetails,
            'hasSearched' => $hasSearched,
        ]);
    }

    private function openingBalanceBase(Collection $cashAccounts, ?int $selectedAccountId): float
    {
        $accounts = $selectedAccountId
            ? $cashAccounts->where('id', $selectedAccountId)
            : $cashAccounts;

        return (float) $accounts->sum(function (Account $account) {
            $amount = (float) ($account->opening_balance ?? 0);

            return ($account->opening_balance_side ?? 'dr') === 'cr' ? -$amount : $amount;
        });
    }
}
