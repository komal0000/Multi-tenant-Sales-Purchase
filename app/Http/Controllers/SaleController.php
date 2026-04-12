<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Account;
use App\Models\Item;
use App\Models\Sale;
use App\Services\PartyCacheService;
use App\Services\SaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $service,
        private readonly PartyCacheService $partyCache,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
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
            || filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null);

        if ($hasSearched) {
            $sales = Sale::query()
                ->with(['party', 'payments'])
                ->when($filters['party_id'] ?? null, fn ($query, $partyId) => $query->where('party_id', $partyId))
                ->when($fromAd, fn ($query) => $query->whereDate('created_at', '>=', $fromAd))
                ->when($toAd, fn ($query) => $query->whereDate('created_at', '<=', $toAd))
                ->latest()
                ->paginate(20)
                ->withQueryString();

            $sales->through(function (Sale $sale) {
                $sale->created_at_bs = DateHelper::adToBs($sale->created_at);
                $sale->received_amount = (float) $sale->payments->where('type', 'received')->sum('amount');

                return $sale;
            });
        } else {
            $sales = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: 20,
                currentPage: 1,
                options: ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('sales.index', [
            'sales' => $sales,
            'parties' => $this->partyCache->all(),
            'filters' => [
                'party_id' => $filters['party_id'] ?? null,
                'from_date_bs' => $filters['from_date_bs'] ?? $todayBs,
                'to_date_bs' => $filters['to_date_bs'] ?? $todayBs,
            ],
            'hasSearched' => $hasSearched,
            'currentBsDateInt' => DateHelper::currentBsInt(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Sale::class);

        $accounts = Account::query()
            ->orderByRaw("case when type = 'cash' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return view('sales.create', [
            'parties' => $this->partyCache->all(),
            'accounts' => $accounts,
            'itemsCatalog' => Item::query()->orderBy('name')->get(['id', 'name', 'qty', 'rate', 'cost_price']),
            'defaultCashAccountId' => $accounts->firstWhere('type', 'cash')?->id,
            'currentBsDateInt' => DateHelper::currentBsInt(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $validated = $request->validated();

        $itemTotal = collect($validated['items'])->sum(fn (array $item) => (float) ($item['qty'] ?? 1) * (float) $item['rate']);
        $paymentTotal = collect($validated['payments'] ?? [])->sum(fn (array $payment) => (float) $payment['amount']);

        if ($paymentTotal > $itemTotal) {
            throw ValidationException::withMessages([
                'payments' => 'Payment total cannot be greater than bill total.',
            ]);
        }

        $sale = $this->service->create($validated);

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', 'Sale created successfully.');
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['party', 'items.item', 'items.expenseCategory']);
        $sale->created_at_bs = DateHelper::adToBs($sale->created_at);
        $sale->received_amount = (float) $sale->payments()->where('type', 'received')->sum('amount');
        $sale->remaining_amount = max(0, (float) $sale->total - $sale->received_amount);

        $linkedPayments = $sale->payments()
            ->with('account')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('sales.show', [
            'sale' => $sale,
            'linkedPayments' => $linkedPayments,
        ]);
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        $this->service->delete($sale);

        return redirect()
            ->route('sales.index')
            ->with('success', 'Sale deleted successfully.');
    }
}
