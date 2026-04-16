<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\BillLineItem;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\LedgerService;
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
        app(LedgerService::class)->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        try {
            [$fromBsInt, $toBsInt] = DateHelper::getBsIntRangeFromFilters($filters['from_date_bs'] ?? null, $filters['to_date_bs'] ?? null);
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
                ->when($fromBsInt, fn ($builder) => $builder->where('date', '<', $fromBsInt))
                ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
                ->value('balance') ?? 0);

            $ledgerRows = (clone $query)
                ->when($fromBsInt, fn ($builder) => $builder->where('date', '>=', $fromBsInt))
                ->when($toBsInt, fn ($builder) => $builder->where('date', '<=', $toBsInt))
                ->orderBy('date')
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
                ? 'Payment / '.($payment->party?->name ?? 'Unknown Party')
                : (ucfirst($row->ref_table).' / '.$row->ref_id);
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
        app(LedgerService::class)->ensureCompatibilitySchema();

        $todayBs = DateHelper::getCurrentBS();
        $startBs = substr($todayBs, 0, 8).'01';

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
                [$fromBsInt, $toBsInt] = DateHelper::getBsIntRangeFromFilters($fromBs, $toBs);
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'from_date_bs' => $exception->getMessage(),
                    'to_date_bs' => $exception->getMessage(),
                ]);
            }

            $salesTotal = (float) Ledger::query()
                ->where('type', 'sale')
                ->where('date', '>=', $fromBsInt)
                ->where('date', '<=', $toBsInt)
                ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as total')
                ->value('total');

            $purchaseTotal = (float) Ledger::query()
                ->where('type', 'purchase')
                ->where('date', '>=', $fromBsInt)
                ->where('date', '<=', $toBsInt)
                ->selectRaw('COALESCE(SUM(cr_amount) - SUM(dr_amount), 0) as total')
                ->value('total');

            $profitLoss = $salesTotal - $purchaseTotal;

            $salesDetails = Sale::query()
                ->with('party:id,name')
                ->where('status', Sale::STATUS_ACTIVE)
                ->where('date', '>=', $fromBsInt)
                ->where('date', '<=', $toBsInt)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

            $purchaseDetails = Purchase::query()
                ->with('party:id,name')
                ->where('status', Purchase::STATUS_ACTIVE)
                ->where('date', '>=', $fromBsInt)
                ->where('date', '<=', $toBsInt)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

            $salesDetails->each(fn (Sale $sale) => $sale->created_at_bs = DateHelper::fromDateInt((int) $sale->date));
            $purchaseDetails->each(fn (Purchase $purchase) => $purchase->created_at_bs = DateHelper::fromDateInt((int) $purchase->date));
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

    public function salesReport(Request $request): View
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        $resolved = $this->resolveReportFilters($request);

        $parties = Party::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $summary = [
            'total_amount' => 0,
            'bill_count' => 0,
            'party_count' => 0,
            'average_bill' => 0,
            'generalized' => [],
            'item_wise' => [],
            'expense_wise' => [],
        ];

        $dateWiseRows = [];
        $partyWiseRows = [];
        $datePartyWiseRows = [];

        if ($resolved['hasSearched']) {
            $sales = Sale::query()
                ->with(['party:id,name,phone', 'items.item:id,name'])
                ->where('status', Sale::STATUS_ACTIVE)
                ->when($resolved['party_id'], fn ($query, $partyId) => $query->where('party_id', $partyId))
                ->when($resolved['from_int'], fn ($query, $fromInt) => $query->where('date', '>=', $fromInt))
                ->when($resolved['to_int'], fn ($query, $toInt) => $query->where('date', '<=', $toInt))
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

            $sales->each(fn (Sale $sale) => $sale->created_at_bs = DateHelper::fromDateInt((int) $sale->date));

            $summary = $this->buildReportSummary($sales, false);
            $dateWiseRows = $this->buildDateWiseRows($sales, false);
            $partyWiseRows = $this->buildPartyWiseRows($sales, false);
            $datePartyWiseRows = $this->buildDatePartyWiseRows($sales, false);
        }

        return view('reports.sales', [
            'parties' => $parties,
            'filters' => [
                'party_id' => $resolved['party_id'],
                'from_date_bs' => $resolved['from_bs'],
                'to_date_bs' => $resolved['to_bs'],
            ],
            'hasSearched' => $resolved['hasSearched'],
            'summary' => $summary,
            'dateWiseRows' => $dateWiseRows,
            'partyWiseRows' => $partyWiseRows,
            'datePartyWiseRows' => $datePartyWiseRows,
        ]);
    }

    public function purchaseReport(Request $request): View
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        $resolved = $this->resolveReportFilters($request);

        $parties = Party::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $summary = [
            'total_amount' => 0,
            'bill_count' => 0,
            'party_count' => 0,
            'average_bill' => 0,
            'generalized' => [],
            'item_wise' => [],
            'expense_wise' => [],
        ];

        $dateWiseRows = [];
        $partyWiseRows = [];
        $datePartyWiseRows = [];

        if ($resolved['hasSearched']) {
            $purchases = Purchase::query()
                ->with(['party:id,name,phone', 'items.item:id,name', 'items.expenseCategory:id,name'])
                ->where('status', Purchase::STATUS_ACTIVE)
                ->when($resolved['party_id'], fn ($query, $partyId) => $query->where('party_id', $partyId))
                ->when($resolved['from_int'], fn ($query, $fromInt) => $query->where('date', '>=', $fromInt))
                ->when($resolved['to_int'], fn ($query, $toInt) => $query->where('date', '<=', $toInt))
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

            $purchases->each(fn (Purchase $purchase) => $purchase->created_at_bs = DateHelper::fromDateInt((int) $purchase->date));

            $summary = $this->buildReportSummary($purchases, true);
            $dateWiseRows = $this->buildDateWiseRows($purchases, true);
            $partyWiseRows = $this->buildPartyWiseRows($purchases, true);
            $datePartyWiseRows = $this->buildDatePartyWiseRows($purchases, true);
        }

        return view('reports.purchases', [
            'parties' => $parties,
            'filters' => [
                'party_id' => $resolved['party_id'],
                'from_date_bs' => $resolved['from_bs'],
                'to_date_bs' => $resolved['to_bs'],
            ],
            'hasSearched' => $resolved['hasSearched'],
            'summary' => $summary,
            'dateWiseRows' => $dateWiseRows,
            'partyWiseRows' => $partyWiseRows,
            'datePartyWiseRows' => $datePartyWiseRows,
        ]);
    }

    public function stockLedgerFifo(Request $request): View
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $todayBs = DateHelper::getCurrentBS();
        $startBs = substr($todayBs, 0, 8).'01';

        $filters = $request->validate([
            'item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        $hasSearched = filled($filters['item_id'] ?? null)
            || filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null);

        $fromBs = $filters['from_date_bs'] ?? $startBs;
        $toBs = $filters['to_date_bs'] ?? $todayBs;

        $fromAd = null;
        $toAd = null;

        if ($hasSearched) {
            try {
                [$fromAd, $toAd] = DateHelper::getAdRangeFromBsFilters($fromBs, $toBs);
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'from_date_bs' => $exception->getMessage(),
                    'to_date_bs' => $exception->getMessage(),
                ]);
            }
        }

        $items = Item::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $reportRows = [];

        if ($hasSearched) {
            $selectedItems = Item::query()
                ->when($filters['item_id'] ?? null, fn ($query, $itemId) => $query->whereKey($itemId))
                ->orderBy('name')
                ->get(['id', 'name']);

            $itemIds = $selectedItems->pluck('id')->all();

            $ledgerRows = ItemLedger::query()
                ->whereIn('item_id', $itemIds)
                ->when($toAd, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $references = $this->buildStockLedgerReferences($ledgerRows);

            $reportRows = $selectedItems
                ->map(function (Item $item) use ($ledgerRows, $references, $fromAd, $toAd): array {
                    $itemLedgerRows = $ledgerRows->where('item_id', $item->id)->values();

                    $openingLayers = [];
                    foreach ($itemLedgerRows as $row) {
                        if ($fromAd && $row->created_at->toDateString() >= $fromAd) {
                            continue;
                        }

                        $this->applyFifoMovement($openingLayers, $row);
                    }

                    $runningLayers = $openingLayers;

                    $periodRows = $itemLedgerRows
                        ->filter(function (ItemLedger $row) use ($fromAd, $toAd): bool {
                            $date = $row->created_at->toDateString();

                            if ($fromAd && $date < $fromAd) {
                                return false;
                            }

                            if ($toAd && $date > $toAd) {
                                return false;
                            }

                            return true;
                        })
                        ->values()
                        ->map(function (ItemLedger $row) use (&$runningLayers, $references): array {
                            $qty = round((float) $row->qty, 4);
                            $rate = round((float) $row->rate, 4);
                            $issueValue = null;

                            if ($row->type === 'in') {
                                $this->pushFifoLayer($runningLayers, $qty, $rate);
                            } else {
                                $issueValue = $this->consumeFifoLayers($runningLayers, $qty, $rate);
                            }

                            return [
                                'date_ad' => $row->created_at->toDateString(),
                                'date_bs' => DateHelper::adToBs($row->created_at->toDateString()),
                                'reference' => $references[$row->id] ?? ucfirst(str_replace('_', ' ', (string) $row->identifier)),
                                'movement' => $row->type === 'in' ? 'In' : 'Out',
                                'in_qty' => $row->type === 'in' ? $qty : null,
                                'out_qty' => $row->type === 'out' ? $qty : null,
                                'rate' => $rate,
                                'issue_value' => $issueValue,
                                'running_qty' => $this->fifoLayerQty($runningLayers),
                            ];
                        })
                        ->all();

                    $closingLayers = collect($runningLayers)
                        ->filter(fn (array $layer) => ($layer['qty'] ?? 0) > 0)
                        ->values()
                        ->map(fn (array $layer) => [
                            'qty' => round((float) $layer['qty'], 4),
                            'rate' => round((float) $layer['rate'], 4),
                            'value' => round((float) $layer['qty'] * (float) $layer['rate'], 2),
                        ])
                        ->all();

                    return [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'opening_qty' => $this->fifoLayerQty($openingLayers),
                        'opening_value' => $this->fifoLayerValue($openingLayers),
                        'rows' => $periodRows,
                        'closing_qty' => $this->fifoLayerQty($runningLayers),
                        'closing_value' => $this->fifoLayerValue($runningLayers),
                        'closing_layers' => $closingLayers,
                    ];
                })
                ->all();
        }

        return view('reports.stock-ledger', [
            'items' => $items,
            'filters' => [
                'item_id' => $filters['item_id'] ?? null,
                'from_date_bs' => $fromBs,
                'to_date_bs' => $toBs,
            ],
            'hasSearched' => $hasSearched,
            'reportRows' => $reportRows,
        ]);
    }

    /**
     * @return array{
     *   party_id: int|null,
     *   from_bs: string,
     *   to_bs: string,
     *   from_int: int|null,
     *   to_int: int|null,
     *   hasSearched: bool
     * }
     */
    private function resolveReportFilters(Request $request): array
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);
        $todayBs = DateHelper::getCurrentBS();
        $startBs = substr($todayBs, 0, 8).'01';

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        $hasSearched = filled($filters['party_id'] ?? null)
            || filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null);

        $fromBs = $filters['from_date_bs'] ?? $startBs;
        $toBs = $filters['to_date_bs'] ?? $todayBs;

        $fromInt = null;
        $toInt = null;

        if ($hasSearched) {
            try {
                [$fromInt, $toInt] = DateHelper::getBsIntRangeFromFilters($fromBs, $toBs);
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'from_date_bs' => $exception->getMessage(),
                    'to_date_bs' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'party_id' => isset($filters['party_id']) ? (int) $filters['party_id'] : null,
            'from_bs' => $fromBs,
            'to_bs' => $toBs,
            'from_int' => $fromInt,
            'to_int' => $toInt,
            'hasSearched' => $hasSearched,
        ];
    }

    /**
     * @param  Collection<int, Sale|Purchase>  $bills
     * @return array<string, mixed>
     */
    private function buildReportSummary(Collection $bills, bool $includeExpense): array
    {
        $lineItems = $bills->flatMap(fn ($bill) => $bill->items);
        $breakdown = $this->buildLineBreakdown($lineItems, $includeExpense);
        $totalAmount = (float) $bills->sum(fn ($bill) => (float) $bill->total);
        $billCount = $bills->count();

        return [
            'total_amount' => $totalAmount,
            'bill_count' => $billCount,
            'party_count' => $bills->pluck('party_id')->filter()->unique()->count(),
            'average_bill' => $billCount > 0 ? $totalAmount / $billCount : 0,
            'generalized' => $breakdown['generalized'],
            'item_wise' => $breakdown['item_wise'],
            'expense_wise' => $breakdown['expense_wise'],
        ];
    }

    /**
     * @param  Collection<int, Sale|Purchase>  $bills
     * @return array<int, array<string, mixed>>
     */
    private function buildDateWiseRows(Collection $bills, bool $includeExpense): array
    {
        return $bills
            ->groupBy(fn ($bill) => (int) $bill->date)
            ->map(function (Collection $dateBills, int $dateInt) use ($includeExpense): array {
                $lineItems = $dateBills->flatMap(fn ($bill) => $bill->items);
                $breakdown = $this->buildLineBreakdown($lineItems, $includeExpense);

                return [
                    'date_bs' => DateHelper::fromDateInt($dateInt),
                    'date_ad' => '',
                    'bill_count' => $dateBills->count(),
                    'party_count' => $dateBills->pluck('party_id')->filter()->unique()->count(),
                    'total_amount' => (float) $dateBills->sum(fn ($bill) => (float) $bill->total),
                    'generalized' => $breakdown['generalized'],
                    'item_wise' => $breakdown['item_wise'],
                    'expense_wise' => $breakdown['expense_wise'],
                ];
            })
            ->sortByDesc('date_bs')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Sale|Purchase>  $bills
     * @return array<int, array<string, mixed>>
     */
    private function buildPartyWiseRows(Collection $bills, bool $includeExpense): array
    {
        return $bills
            ->groupBy(fn ($bill) => (string) $bill->party_id)
            ->map(function (Collection $partyBills, string $partyId) use ($includeExpense): array {
                $lineItems = $partyBills->flatMap(fn ($bill) => $bill->items);
                $breakdown = $this->buildLineBreakdown($lineItems, $includeExpense);
                $party = $partyBills->first()?->party;

                return [
                    'party_id' => (int) $partyId,
                    'party_name' => $party?->name ?? 'Unknown Party',
                    'party_phone' => $party?->phone,
                    'bill_count' => $partyBills->count(),
                    'date_count' => $partyBills->groupBy(fn ($bill) => (int) $bill->date)->count(),
                    'total_amount' => (float) $partyBills->sum(fn ($bill) => (float) $bill->total),
                    'generalized' => $breakdown['generalized'],
                    'item_wise' => $breakdown['item_wise'],
                    'expense_wise' => $breakdown['expense_wise'],
                ];
            })
            ->sortByDesc('total_amount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Sale|Purchase>  $bills
     * @return array<int, array<string, mixed>>
     */
    private function buildDatePartyWiseRows(Collection $bills, bool $includeExpense): array
    {
        return $bills
            ->groupBy(fn ($bill) => (int) $bill->date)
            ->map(function (Collection $dateBills, int $dateInt) use ($includeExpense): array {
                $partyRows = $dateBills
                    ->groupBy(fn ($bill) => (string) $bill->party_id)
                    ->map(function (Collection $partyBills, string $partyId) use ($includeExpense): array {
                        $lineItems = $partyBills->flatMap(fn ($bill) => $bill->items);
                        $breakdown = $this->buildLineBreakdown($lineItems, $includeExpense);
                        $party = $partyBills->first()?->party;

                        return [
                            'party_id' => (int) $partyId,
                            'party_name' => $party?->name ?? 'Unknown Party',
                            'party_phone' => $party?->phone,
                            'bill_count' => $partyBills->count(),
                            'total_amount' => (float) $partyBills->sum(fn ($bill) => (float) $bill->total),
                            'generalized' => $breakdown['generalized'],
                            'item_wise' => $breakdown['item_wise'],
                            'expense_wise' => $breakdown['expense_wise'],
                        ];
                    })
                    ->sortByDesc('total_amount')
                    ->values()
                    ->all();

                return [
                    'date_bs' => DateHelper::fromDateInt($dateInt),
                    'date_ad' => '',
                    'bill_count' => $dateBills->count(),
                    'party_count' => count($partyRows),
                    'total_amount' => (float) $dateBills->sum(fn ($bill) => (float) $bill->total),
                    'parties' => $partyRows,
                ];
            })
            ->sortByDesc('date_bs')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $lineItems
     * @return array{generalized: array<int, array<string, mixed>>, item_wise: array<int, array<string, mixed>>, expense_wise: array<int, array<string, mixed>>}
     */
    private function buildLineBreakdown(Collection $lineItems, bool $includeExpense): array
    {
        $generalized = $this->groupLineRows(
            $lineItems->where('line_type', 'general'),
            fn ($lineItem) => filled($lineItem->description ?? null)
                ? trim((string) $lineItem->description)
                : 'General'
        );

        $itemWise = $this->groupLineRows(
            $lineItems->where('line_type', 'item'),
            fn ($lineItem) => $lineItem->item?->name
                ?? (filled($lineItem->description ?? null) ? trim((string) $lineItem->description) : 'Unknown Item')
        );

        $expenseWise = $includeExpense
            ? $this->groupLineRows(
                $lineItems->where('line_type', 'expense'),
                fn ($lineItem) => $lineItem->expenseCategory?->name
                    ?? (filled($lineItem->description ?? null) ? trim((string) $lineItem->description) : 'Uncategorized Expense')
            )
            : [];

        return [
            'generalized' => $generalized,
            'item_wise' => $itemWise,
            'expense_wise' => $expenseWise,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupLineRows(Collection $rows, callable $labelResolver): array
    {
        return $rows
            ->groupBy(fn ($row) => (string) $labelResolver($row))
            ->map(function (Collection $lineRows, string $label): array {
                return [
                    'label' => $label,
                    'qty' => (float) $lineRows->sum(fn ($lineRow) => (float) ($lineRow->qty ?? 0)),
                    'amount' => (float) $lineRows->sum(fn ($lineRow) => (float) ($lineRow->total ?? 0)),
                    'line_count' => $lineRows->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
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

    /**
     * @param  Collection<int, ItemLedger>  $ledgerRows
     * @return array<int, string>
     */
    private function buildStockLedgerReferences(Collection $ledgerRows): array
    {
        $lineItemIds = $ledgerRows
            ->whereIn('identifier', ['sale', 'purchase'])
            ->pluck('foreign_key')
            ->filter()
            ->unique()
            ->all();

        $lineItems = BillLineItem::query()
            ->with(['item:id,name', 'expenseCategory:id,name'])
            ->whereIn('id', $lineItemIds)
            ->get()
            ->keyBy('id');

        $sales = Sale::query()
            ->withTrashed()
            ->with('party:id,name')
            ->whereIn('id', $lineItems->where('bill_type', 'sale')->pluck('bill_id')->unique())
            ->get()
            ->keyBy('id');

        $purchases = Purchase::query()
            ->withTrashed()
            ->with('party:id,name')
            ->whereIn('id', $lineItems->where('bill_type', 'purchase')->pluck('bill_id')->unique())
            ->get()
            ->keyBy('id');

        $references = [];

        foreach ($ledgerRows as $row) {
            if ($row->identifier === 'opening_stock') {
                $references[$row->id] = 'Opening Stock';

                continue;
            }

            $lineItem = $lineItems->get($row->foreign_key);

            if ($row->identifier === 'sale' && $lineItem) {
                $sale = $sales->get($lineItem->bill_id);
                $references[$row->id] = sprintf(
                    'Sale #%d / %s / %s',
                    (int) $lineItem->bill_id,
                    $sale?->party?->name ?? 'Unknown Party',
                    $lineItem->line_label
                );

                continue;
            }

            if ($row->identifier === 'purchase' && $lineItem) {
                $purchase = $purchases->get($lineItem->bill_id);
                $references[$row->id] = sprintf(
                    'Purchase #%d / %s / %s',
                    (int) $lineItem->bill_id,
                    $purchase?->party?->name ?? 'Unknown Party',
                    $lineItem->line_label
                );

                continue;
            }

            $references[$row->id] = ucfirst(str_replace('_', ' ', (string) $row->identifier));
        }

        return $references;
    }

    /**
     * @param  array<int, array{qty: float, rate: float}>  $layers
     */
    private function applyFifoMovement(array &$layers, ItemLedger $row): void
    {
        $qty = round((float) $row->qty, 4);
        $rate = round((float) $row->rate, 4);

        if ($row->type === 'in') {
            $this->pushFifoLayer($layers, $qty, $rate);

            return;
        }

        $this->consumeFifoLayers($layers, $qty, $rate);
    }

    /**
     * @param  array<int, array{qty: float, rate: float}>  $layers
     */
    private function pushFifoLayer(array &$layers, float $qty, float $rate): void
    {
        $qty = round($qty, 4);

        if ($qty <= 0) {
            return;
        }

        $layers[] = [
            'qty' => $qty,
            'rate' => round($rate, 4),
        ];
    }

    /**
     * @param  array<int, array{qty: float, rate: float}>  $layers
     */
    private function consumeFifoLayers(array &$layers, float $qty, float $fallbackRate): float
    {
        $remaining = round($qty, 4);
        $value = 0.0;

        while ($remaining > 0.0000 && $layers !== []) {
            if (($layers[0]['qty'] ?? 0) <= 0.0000) {
                array_shift($layers);

                continue;
            }

            $take = min($remaining, (float) $layers[0]['qty']);
            $value += $take * (float) $layers[0]['rate'];
            $layers[0]['qty'] = round((float) $layers[0]['qty'] - $take, 4);
            $remaining = round($remaining - $take, 4);

            if ((float) $layers[0]['qty'] <= 0.0000) {
                array_shift($layers);
            }
        }

        if ($remaining > 0.0000) {
            $value += $remaining * $fallbackRate;
        }

        return round($value, 2);
    }

    /**
     * @param  array<int, array{qty: float, rate: float}>  $layers
     */
    private function fifoLayerQty(array $layers): float
    {
        return round((float) collect($layers)->sum(fn (array $layer) => (float) ($layer['qty'] ?? 0)), 4);
    }

    /**
     * @param  array<int, array{qty: float, rate: float}>  $layers
     */
    private function fifoLayerValue(array $layers): float
    {
        return round((float) collect($layers)->sum(fn (array $layer) => ((float) ($layer['qty'] ?? 0)) * ((float) ($layer['rate'] ?? 0))), 2);
    }
}
