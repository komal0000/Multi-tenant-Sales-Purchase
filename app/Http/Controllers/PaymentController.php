<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StoreMassPaymentRowRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\PayrollSetting;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\LedgerService;
use App\Services\PartyCacheService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $service,
        private readonly PartyCacheService $partyCache,
        private readonly LedgerService $ledger,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'type' => ['nullable', 'in:received,given'],
            'keyword' => ['nullable', 'string', 'max:80'],
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        $todayBs = DateHelper::getCurrentBS();

        try {
            [$fromBsInt, $toBsInt] = DateHelper::getBsIntRangeFromFilters($filters['from_date_bs'] ?? null, $filters['to_date_bs'] ?? null);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'from_date_bs' => $exception->getMessage(),
                'to_date_bs' => $exception->getMessage(),
            ]);
        }

        $hasSearched = filled($filters['party_id'] ?? null)
            || filled($filters['account_id'] ?? null)
            || filled($filters['type'] ?? null)
            || filled($filters['keyword'] ?? null)
            || filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null);

        if ($hasSearched) {
            $payments = Payment::query()
                ->with(['party', 'account', 'sale', 'purchase'])
                ->when($filters['party_id'] ?? null, fn ($query, $partyId) => $query->where('party_id', $partyId))
                ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
                ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->when($fromBsInt, fn ($query) => $query->where('date', '>=', $fromBsInt))
                ->when($toBsInt, fn ($query) => $query->where('date', '<=', $toBsInt))
                ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                    $term = '%'.trim((string) $keyword).'%';

                    $query->where(function ($subQuery) use ($term) {
                        $subQuery
                            ->where('cheque_number', 'like', $term)
                            ->orWhere('notes', 'like', $term)
                            ->orWhereHas('party', fn ($partyQuery) => $partyQuery->where('name', 'like', $term))
                            ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('name', 'like', $term));
                    });
                })
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();

            $payments->through(function (Payment $payment) {
                $payment->created_at_bs = DateHelper::fromDateInt((int) $payment->date);

                return $payment;
            });
        } else {
            $payments = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: 20,
                currentPage: 1,
                options: ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('payments.index', [
            'payments' => $payments,
            'parties' => $this->partyCache->all(),
            'accounts' => $this->orderedAccounts(),
            'filters' => [
                'party_id' => $filters['party_id'] ?? null,
                'account_id' => $filters['account_id'] ?? null,
                'type' => $filters['type'] ?? null,
                'keyword' => $filters['keyword'] ?? null,
                'from_date_bs' => $filters['from_date_bs'] ?? $todayBs,
                'to_date_bs' => $filters['to_date_bs'] ?? $todayBs,
            ],
            'hasSearched' => $hasSearched,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $saleId = old('sale_id', $request->string('sale_id')->toString());
        $purchaseId = old('purchase_id', $request->string('purchase_id')->toString());

        $sale = filled($saleId)
            ? Sale::query()->with('party')->where('status', Sale::STATUS_ACTIVE)->find($saleId)
            : null;
        $purchase = filled($purchaseId)
            ? Purchase::query()->with('party')->where('status', Purchase::STATUS_ACTIVE)->find($purchaseId)
            : null;
        $selectedPartyId = old('party_id', $request->string('party_id')->toString() ?: ($sale?->party_id ?? $purchase?->party_id));
        $selectedType = old('type');

        if ($selectedType === null) {
            if ($sale) {
                $selectedType = 'received';
            } elseif ($purchase) {
                $selectedType = 'given';
            } else {
                $requestedType = $request->string('type')->toString();
                $selectedType = filled($requestedType)
                    ? $requestedType
                    : $this->defaultStandaloneDirectionForParty(
                        filled($selectedPartyId) ? (int) $selectedPartyId : null
                    );
            }
        }

        $selectedDateBs = old('date_bs', $request->string('date_bs')->toString() ?: DateHelper::getCurrentBS());
        $accounts = $this->orderedAccounts();
        $defaultCashAccountId = $accounts->firstWhere('type', 'cash')?->id;

        return view('payments.create', [
            'parties' => $this->partyCache->all(),
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'accountsCreateUrl' => route('accounts.create'),
            'selectedSaleOption' => $sale
                ? [
                    'id' => $sale->id,
                    'text' => $this->billOptionText($sale->id, $sale->party?->name, (float) $sale->total),
                ]
                : null,
            'selectedPurchaseOption' => $purchase
                ? [
                    'id' => $purchase->id,
                    'text' => $this->billOptionText($purchase->id, $purchase->party?->name, (float) $purchase->total),
                ]
                : null,
            'selectedPartyId' => $selectedPartyId,
            'selectedAccountId' => old('account_id', $defaultCashAccountId),
            'selectedType' => $selectedType,
            'selectedDateBs' => $selectedDateBs,
            'selectedSaleId' => old('sale_id', $sale?->id),
            'selectedPurchaseId' => old('purchase_id', $purchase?->id),
        ]);
    }

    public function createMass(Request $request): View
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $accounts = $this->orderedAccounts();

        return view('payments.mass-unified', [
            'parties' => $this->partyCache->all(),
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'accountsCreateUrl' => route('accounts.create'),
            'dateBs' => old('date_bs', DateHelper::getCurrentBS()),
        ]);
    }

    public function loadMassRows(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validate([
            'date_bs' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ]);

        $payments = Payment::query()
            ->with(['party', 'account', 'sale', 'purchase'])
            ->where('date', DateHelper::toDateInt($validated['date_bs']))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'rows' => $payments->map(fn (Payment $payment) => $this->massPaymentRow($payment, $request))->values(),
            'total_entries' => $payments->count(),
            'total_amount' => number_format((float) $payments->sum('amount'), 2, '.', ''),
        ]);
    }

    public function storeMassRow(StoreMassPaymentRowRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();
        $payment = $this->service->create([
            'date' => DateHelper::toDateInt($validated['date_bs']),
            'party_id' => $validated['party_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'account_id' => $validated['account_id'],
            'cheque_number' => null,
            'sale_id' => null,
            'purchase_id' => null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Mass payment row saved successfully.',
            'row' => $this->massPaymentRow($payment, $request),
        ], 201);
    }

    public function updateMassRow(StoreMassPaymentRowRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('update', $payment);
        $this->ledger->ensureCompatibilitySchema();
        $this->ensureStandaloneMassPayment($payment);

        $validated = $request->validated();
        $payment = $this->service->updateStandalone($payment, [
            'date' => DateHelper::toDateInt($validated['date_bs']),
            'party_id' => $validated['party_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'account_id' => $validated['account_id'],
            'cheque_number' => null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Mass payment row updated successfully.',
            'row' => $this->massPaymentRow($payment, $request),
        ]);
    }

    public function destroyMassRow(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);
        $this->ledger->ensureCompatibilitySchema();
        $this->ensureStandaloneMassPayment($payment);

        $this->service->deleteStandalone($payment);

        return response()->json([
            'message' => 'Mass payment row deleted successfully.',
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();

        $validated['type'] = ! empty($validated['sale_id'])
            ? 'received'
            : (! empty($validated['purchase_id']) ? 'given' : ($validated['type'] ?? 'received'));
        $validated['date'] = filled($validated['date_bs'] ?? null)
            ? DateHelper::toDateInt((string) $validated['date_bs'])
            : DateHelper::currentBsInt();

        $payment = $this->service->create($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Payment created successfully.',
                'payment' => [
                    'id' => $payment->id,
                    'party_id' => $payment->party_id,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'type' => $payment->type,
                    'account_id' => $payment->account_id,
                    'cheque_number' => $payment->cheque_number,
                    'notes' => $payment->notes,
                    'date_bs' => DateHelper::fromDateInt((int) $payment->date),
                ],
            ], 201);
        }

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Payment created successfully.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);
        $this->ledger->ensureCompatibilitySchema();

        $payment->load(['party', 'account', 'sale', 'purchase']);
        $payment->created_at_bs = DateHelper::fromDateInt((int) $payment->date);

        return view('payments.show', [
            'payment' => $payment,
        ]);
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);
        $this->ledger->ensureCompatibilitySchema();

        $this->service->delete($payment);

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment deleted successfully and ledger removed.');
    }

    public function searchSales(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);
        $this->ledger->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! filled($filters['party_id'] ?? null)) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $sales = Sale::query()
            ->with('party:id,name')
            ->where('party_id', $filters['party_id'])
            ->where('status', Sale::STATUS_ACTIVE)
            ->when($filters['q'] ?? null, function ($query, $keyword) {
                $term = trim((string) $keyword);

                $query->where(function ($subQuery) use ($term) {
                    if (is_numeric($term)) {
                        $subQuery
                            ->whereKey((int) $term)
                            ->orWhere('total', (float) $term)
                            ->orWhere('total', 'like', '%'.$term.'%');

                        return;
                    }

                    $subQuery->where('total', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['id', 'party_id', 'total'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'results' => $sales->getCollection()
                ->map(fn (Sale $sale) => [
                    'id' => (string) $sale->id,
                    'text' => $this->billOptionText($sale->id, $sale->party?->name, (float) $sale->total),
                ])
                ->values(),
            'pagination' => ['more' => $sales->hasMorePages()],
        ]);
    }

    public function searchPurchases(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);
        $this->ledger->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! filled($filters['party_id'] ?? null)) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $purchases = Purchase::query()
            ->with('party:id,name')
            ->where('party_id', $filters['party_id'])
            ->where('status', Purchase::STATUS_ACTIVE)
            ->when($filters['q'] ?? null, function ($query, $keyword) {
                $term = trim((string) $keyword);

                $query->where(function ($subQuery) use ($term) {
                    if (is_numeric($term)) {
                        $subQuery
                            ->whereKey((int) $term)
                            ->orWhere('total', (float) $term)
                            ->orWhere('total', 'like', '%'.$term.'%');

                        return;
                    }

                    $subQuery->where('total', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['id', 'party_id', 'total'], 'page', $filters['page'] ?? 1);

        return response()->json([
            'results' => $purchases->getCollection()
                ->map(fn (Purchase $purchase) => [
                    'id' => (string) $purchase->id,
                    'text' => $this->billOptionText($purchase->id, $purchase->party?->name, (float) $purchase->total),
                ])
                ->values(),
            'pagination' => ['more' => $purchases->hasMorePages()],
        ]);
    }

    public function partyBalance(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $validated = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);

        if (! filled($validated['party_id'] ?? null)) {
            return response()->json([
                'balance' => null,
                'formatted_amount' => '0.00',
                'label' => 'No party selected',
                'direction' => 'received',
                'tone' => 'neutral',
                'recent_entries' => [],
                'sidebar_limit' => $this->paymentSidebarLimit(),
            ]);
        }

        $balance = (float) $this->ledger->partyBalance((string) $validated['party_id']);
        $sidebarLimit = $this->paymentSidebarLimit();
        $recentEntries = Ledger::query()
            ->where('party_id', $validated['party_id'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($sidebarLimit)
            ->get();

        $this->ledger->attachReferenceText($recentEntries);

        return response()->json([
            'balance' => $balance,
            'formatted_amount' => number_format(abs($balance), 2),
            'label' => $balance > 0 ? 'Receivable' : ($balance < 0 ? 'Payable' : 'Settled'),
            'direction' => $this->defaultStandaloneDirectionForParty((int) $validated['party_id'], $balance),
            'tone' => $balance > 0 ? 'positive' : ($balance < 0 ? 'negative' : 'neutral'),
            'sidebar_limit' => $sidebarLimit,
            'recent_entries' => $recentEntries->map(fn (Ledger $entry) => [
                'date' => DateHelper::fromDateInt((int) $entry->date),
                'type' => str_replace('_', ' ', $entry->type),
                'reference' => $entry->reference_text ?? ($entry->ref_table.' / '.$entry->ref_id),
                'receivable' => (float) $entry->dr_amount > 0 ? number_format((float) $entry->dr_amount, 2) : null,
                'payable' => (float) $entry->cr_amount > 0 ? number_format((float) $entry->cr_amount, 2) : null,
            ])->values(),
        ]);
    }

    private function billOptionText(int|string $id, ?string $partyName, float $total): string
    {
        return sprintf('#%d | %s | %s', (int) $id, $partyName ?? 'Unknown Party', number_format($total, 2));
    }

    private function orderedAccounts()
    {
        return Account::query()
            ->orderByRaw("case when type = 'cash' then 0 else 1 end")
            ->orderBy('name')
            ->get();
    }

    private function paymentSidebarLimit(): int
    {
        return (int) (PayrollSetting::query()->firstOrCreate([], [
            'leave_fine_per_day' => 0,
            'overtime_money_per_day' => 0,
            'payment_sidebar_limit' => 10,
        ])->payment_sidebar_limit ?? 10);
    }

    private function defaultStandaloneDirectionForParty(?int $partyId, ?float $balance = null): string
    {
        if (! $partyId) {
            return 'received';
        }

        $currentBalance = $balance ?? (float) $this->ledger->partyBalance((string) $partyId);
        $epsilon = 0.00001;

        if ($currentBalance < -$epsilon) {
            return 'given';
        }

        if ($currentBalance > $epsilon) {
            return 'received';
        }

        $isEmployeeParty = Party::query()
            ->whereKey($partyId)
            ->whereHas('employees')
            ->exists();

        return $isEmployeeParty ? 'given' : 'received';
    }

    private function ensureStandaloneMassPayment(Payment $payment): void
    {
        if ($payment->sale_id || $payment->purchase_id) {
            throw ValidationException::withMessages([
                'payment' => 'Linked bill payments cannot be changed from mass payment.',
            ]);
        }
    }

    private function massPaymentRow(Payment $payment, Request $request): array
    {
        $payment->loadMissing(['party', 'account', 'sale', 'purchase']);

        $isLinked = filled($payment->sale_id) || filled($payment->purchase_id);
        $linkedLabel = 'Advance';

        if ($payment->sale) {
            $linkedLabel = 'Sale #'.$payment->sale->id.' / '.number_format((float) $payment->sale->total, 2);
        } elseif ($payment->purchase) {
            $linkedLabel = 'Purchase #'.$payment->purchase->id.' / '.number_format((float) $payment->purchase->total, 2);
        }

        return [
            'id' => $payment->id,
            'type' => $payment->type,
            'party_id' => (string) $payment->party_id,
            'party_label' => $payment->party?->name ?? 'Unknown Party',
            'account_id' => (string) $payment->account_id,
            'account_label' => $payment->account
                ? sprintf('%s (%s)', $payment->account->name, ucfirst($payment->account->type))
                : 'Unknown Account',
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'notes' => (string) ($payment->notes ?? ''),
            'date_bs' => DateHelper::fromDateInt((int) $payment->date),
            'is_linked' => $isLinked,
            'linked_label' => $linkedLabel,
            'can_edit' => ! $isLinked && (bool) ($request->user()?->can('update', $payment)),
            'can_delete' => ! $isLinked && (bool) ($request->user()?->can('delete', $payment)),
        ];
    }
}
