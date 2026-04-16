<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StoreMassPaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Account;
use App\Models\Ledger;
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
            [$fromAd, $toAd] = DateHelper::getAdRangeFromBsFilters($filters['from_date_bs'] ?? null, $filters['to_date_bs'] ?? null);
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
                ->when($fromAd, fn ($query) => $query->whereDate('created_at', '>=', $fromAd))
                ->when($toAd, fn ($query) => $query->whereDate('created_at', '<=', $toAd))
                ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                    $term = '%'.trim((string) $keyword).'%';

                    $query->where(function ($subQuery) use ($term) {
                        $subQuery
                            ->where('cheque_number', 'like', $term)
                            ->orWhereHas('party', fn ($partyQuery) => $partyQuery->where('name', 'like', $term))
                            ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('name', 'like', $term));
                    });
                })
                ->latest()
                ->paginate(20)
                ->withQueryString();

            $payments->through(function (Payment $payment) {
                $payment->created_at_bs = DateHelper::adToBs($payment->created_at);

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
            'accounts' => Account::query()->orderByRaw("case when type = 'cash' then 0 else 1 end")->orderBy('name')->get(),
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
        $selectedType = old(
            'type',
            $sale
                ? 'received'
                : ($purchase ? 'given' : ($request->string('type')->toString() ?: 'received'))
        );
        $accounts = Account::query()
            ->orderByRaw("case when type = 'cash' then 0 else 1 end")
            ->orderBy('name')
            ->get();
        $defaultCashAccountId = $accounts->firstWhere('type', 'cash')?->id;

        return view('payments.create', [
            'parties' => $this->partyCache->all(),
            'accounts' => $accounts,
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
            'selectedSaleId' => old('sale_id', $sale?->id),
            'selectedPurchaseId' => old('purchase_id', $purchase?->id),
        ]);
    }

    public function createMassReceived(Request $request): View
    {
        return $this->renderMassPaymentCreate($request, 'received');
    }

    public function storeMassReceived(StoreMassPaymentRequest $request): RedirectResponse
    {
        return $this->storeMassPayment($request, 'received');
    }

    public function createMassGiven(Request $request): View
    {
        return $this->renderMassPaymentCreate($request, 'given');
    }

    public function storeMassGiven(StoreMassPaymentRequest $request): RedirectResponse
    {
        return $this->storeMassPayment($request, 'given');
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();

        $validated['type'] = ! empty($validated['sale_id'])
            ? 'received'
            : (! empty($validated['purchase_id']) ? 'given' : ($validated['type'] ?? 'received'));

        $payment = $this->service->create($validated);

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Payment created successfully.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);
        $this->ledger->ensureCompatibilitySchema();

        $payment->load(['party', 'account', 'sale', 'purchase']);
        $payment->created_at_bs = DateHelper::adToBs($payment->created_at);

        return view('payments.show', [
            'payment' => $payment,
        ]);
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);
        $this->ledger->ensureCompatibilitySchema();

        $this->ledger->removeEntries('payments', $payment->id);
        $payment->delete();

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
            ->latest()
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
            ->latest()
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
            ->latest('created_at')
            ->latest('id')
            ->limit($sidebarLimit)
            ->get();

        $this->ledger->attachReferenceText($recentEntries);

        return response()->json([
            'balance' => $balance,
            'formatted_amount' => number_format(abs($balance), 2),
            'label' => $balance > 0 ? 'Receivable' : ($balance < 0 ? 'Payable' : 'Settled'),
            'direction' => $balance < 0 ? 'given' : 'received',
            'tone' => $balance > 0 ? 'positive' : ($balance < 0 ? 'negative' : 'neutral'),
            'sidebar_limit' => $sidebarLimit,
            'recent_entries' => $recentEntries->map(fn (Ledger $entry) => [
                'date' => $entry->created_at->format('d M Y'),
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

    private function renderMassPaymentCreate(Request $request, string $type): View
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        return view('payments.mass-create', [
            'paymentType' => $type,
            'pageTitle' => $type === 'received' ? 'Mass Received' : 'Mass Given',
            'submitRoute' => $type === 'received'
                ? route('payments.mass-received.store')
                : route('payments.mass-given.store'),
            'parties' => $this->partyCache->all(),
            'accounts' => Account::query()
                ->orderByRaw("case when type = 'cash' then 0 else 1 end")
                ->orderBy('name')
                ->get(),
            'dateBs' => old('date_bs', DateHelper::getCurrentBS()),
            'initialRows' => collect(old('rows', []))
                ->map(fn ($row) => [
                    'party_id' => filled($row['party_id'] ?? null) ? (string) $row['party_id'] : '',
                    'account_id' => filled($row['account_id'] ?? null) ? (string) $row['account_id'] : '',
                    'amount' => (float) ($row['amount'] ?? 0),
                    'notes' => (string) ($row['notes'] ?? ''),
                ])
                ->filter(fn (array $row) => $row['party_id'] !== '' || $row['account_id'] !== '' || $row['amount'] > 0 || $row['notes'] !== '')
                ->values()
                ->all(),
        ]);
    }

    private function storeMassPayment(StoreMassPaymentRequest $request, string $type): RedirectResponse
    {
        $this->authorize('create', Payment::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();
        $paymentDateAd = DateHelper::bsToAd($validated['date_bs']);
        $payments = $this->service->createBatch($validated['rows'], $type, $paymentDateAd);

        return redirect()
            ->route('payments.index')
            ->with('success', sprintf('%d %s payments created successfully.', $payments->count(), $type));
    }

    private function paymentSidebarLimit(): int
    {
        return (int) (PayrollSetting::query()->firstOrCreate([], [
            'leave_fine_per_day' => 0,
            'overtime_money_per_day' => 0,
            'payment_sidebar_limit' => 10,
        ])->payment_sidebar_limit ?? 10);
    }
}
