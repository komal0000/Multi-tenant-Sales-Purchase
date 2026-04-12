<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
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
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);

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
                    $term = '%' . trim((string) $keyword) . '%';

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

        $saleId = old('sale_id', $request->string('sale_id')->toString());
        $purchaseId = old('purchase_id', $request->string('purchase_id')->toString());

        $sale = filled($saleId) ? Sale::query()->with('party')->find($saleId) : null;
        $purchase = filled($purchaseId) ? Purchase::query()->with('party')->find($purchaseId) : null;
        $selectedPartyId = old('party_id', $request->string('party_id')->toString() ?: ($sale?->party_id ?? $purchase?->party_id));
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
            'selectedSaleId' => old('sale_id', $sale?->id),
            'selectedPurchaseId' => old('purchase_id', $purchase?->id),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validated();

        $validated['type'] = !empty($validated['sale_id'])
            ? 'received'
            : (!empty($validated['purchase_id']) ? 'given' : 'received');

        $payment = $this->service->create($validated);

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Payment created successfully.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load(['party', 'account', 'sale', 'purchase']);
        $payment->created_at_bs = DateHelper::adToBs($payment->created_at);

        return view('payments.show', [
            'payment' => $payment,
        ]);
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        $this->service->delete($payment);

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment deleted successfully and ledger reversed.');
    }

    public function searchSales(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (!filled($filters['party_id'] ?? null)) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $sales = Sale::query()
            ->with('party:id,name')
            ->where('party_id', $filters['party_id'])
            ->when($filters['q'] ?? null, function ($query, $keyword) {
                $term = trim((string) $keyword);

                $query->where(function ($subQuery) use ($term) {
                    if (is_numeric($term)) {
                        $subQuery
                            ->whereKey((int) $term)
                            ->orWhere('total', (float) $term)
                            ->orWhere('total', 'like', '%' . $term . '%');

                        return;
                    }

                    $subQuery->where('total', 'like', '%' . $term . '%');
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

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (!filled($filters['party_id'] ?? null)) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $purchases = Purchase::query()
            ->with('party:id,name')
            ->where('party_id', $filters['party_id'])
            ->when($filters['q'] ?? null, function ($query, $keyword) {
                $term = trim((string) $keyword);

                $query->where(function ($subQuery) use ($term) {
                    if (is_numeric($term)) {
                        $subQuery
                            ->whereKey((int) $term)
                            ->orWhere('total', (float) $term)
                            ->orWhere('total', 'like', '%' . $term . '%');

                        return;
                    }

                    $subQuery->where('total', 'like', '%' . $term . '%');
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

    private function billOptionText(int|string $id, ?string $partyName, float $total): string
    {
        return sprintf('#%d | %s | %s', (int) $id, $partyName ?? 'Unknown Party', number_format($total, 2));
    }
}
