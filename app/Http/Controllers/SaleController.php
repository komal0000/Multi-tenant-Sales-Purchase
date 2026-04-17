<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Account;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\LedgerService;
use App\Services\PartyCacheService;
use App\Services\SaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $service,
        private readonly PartyCacheService $partyCache,
        private readonly LedgerService $ledger,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);
        $this->ledger->ensureCompatibilitySchema();

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $filters = $request->validate([
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'keyword' => ['nullable', 'string', 'max:120'],
            'from_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'to_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'show_cancelled' => ['nullable', 'boolean'],
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
            || filled($filters['keyword'] ?? null)
            || filled($filters['from_date_bs'] ?? null)
            || filled($filters['to_date_bs'] ?? null)
            || filled($filters['show_cancelled'] ?? null);

        if ($hasSearched) {
            $showCancelled = (bool) ($filters['show_cancelled'] ?? false);

            $sales = Sale::query()
                ->with(['party', 'payments'])
                ->when(! $showCancelled, fn ($query) => $query->where('status', Sale::STATUS_ACTIVE))
                ->when($filters['party_id'] ?? null, fn ($query, $partyId) => $query->where('party_id', $partyId))
                ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                    $term = trim((string) $keyword);
                    $likeTerm = '%'.$term.'%';

                    $query->where(function ($subQuery) use ($term, $likeTerm) {
                        if (is_numeric($term)) {
                            $subQuery
                                ->whereKey((int) $term)
                                ->orWhere('total', (float) $term)
                                ->orWhere('total', 'like', $likeTerm);
                        } else {
                            $subQuery->where('total', 'like', $likeTerm);
                        }

                        $subQuery->orWhereHas('party', function ($partyQuery) use ($likeTerm) {
                            $partyQuery
                                ->where('name', 'like', $likeTerm)
                                ->orWhere('phone', 'like', $likeTerm);
                        });
                    });
                })
                ->when($fromBsInt, fn ($query) => $query->where('date', '>=', $fromBsInt))
                ->when($toBsInt, fn ($query) => $query->where('date', '<=', $toBsInt))
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();

            $sales->through(function (Sale $sale) {
                $sale->created_at_bs = DateHelper::fromDateInt((int) $sale->date);
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
                'keyword' => $filters['keyword'] ?? null,
                'from_date_bs' => $filters['from_date_bs'] ?? $todayBs,
                'to_date_bs' => $filters['to_date_bs'] ?? $todayBs,
                'show_cancelled' => (bool) ($filters['show_cancelled'] ?? false),
            ],
            'hasSearched' => $hasSearched,
            'currentBsDateInt' => DateHelper::currentBsInt(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Sale::class);
        $this->ledger->ensureCompatibilitySchema();

        $accounts = Account::query()
            ->orderByRaw("case when type = 'cash' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return view('sales.create', [
            'parties' => $this->partyCache->all(),
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'accountsCreateUrl' => route('accounts.create'),
            'itemsCatalog' => Item::query()->orderBy('name')->get(['id', 'name', 'qty', 'rate', 'cost_price']),
            'defaultCashAccountId' => $accounts->firstWhere('type', 'cash')?->id,
            'currentBsDate' => DateHelper::getCurrentBS(),
            'currentBsDateInt' => DateHelper::currentBsInt(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();
        $validated['date'] = DateHelper::toDateInt($validated['date_bs']);

        $itemTotal = collect($validated['items'])->sum(fn (array $item) => (float) ($item['qty'] ?? 1) * (float) $item['rate']);
        $paymentTotal = collect($validated['payments'] ?? [])->sum(fn (array $payment) => (float) $payment['amount']);

        if ($paymentTotal > $itemTotal) {
            throw ValidationException::withMessages([
                'payments' => 'Payment total cannot be greater than bill total.',
            ]);
        }

        $this->service->create($validated);

        return redirect()
            ->route('sales.create')
            ->with('success', 'Sale created successfully.');
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);
        $this->ledger->ensureCompatibilitySchema();

        $sale->load(['party', 'items.item', 'items.expenseCategory']);
        $sale->created_at_bs = DateHelper::fromDateInt((int) $sale->date);
        $sale->received_amount = (float) $sale->payments()->where('type', 'received')->sum('amount');
        $sale->remaining_amount = max(0, (float) $sale->total - $sale->received_amount);

        $linkedPayments = $sale->payments()
            ->with('account')
            ->orderByDesc('date')
            ->orderByDesc('id')
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
        $this->ledger->ensureCompatibilitySchema();

        DB::transaction(function () use ($sale): void {
            if ($sale->status === Sale::STATUS_CANCELLED) {
                return;
            }

            $sale->loadMissing([
                'payments' => fn ($query) => $query->withTrashed(),
                'items.item',
            ]);

            $paymentIds = $sale->payments->pluck('id')->all();

            $this->ledger->removeEntries('sales', $sale->id);
            $this->ledger->removeEntries('payments', $paymentIds);

            if ($paymentIds !== []) {
                Payment::withTrashed()
                    ->whereIn('id', $paymentIds)
                    ->delete();
            }

            $itemLineIds = $sale->items
                ->where('line_type', 'item')
                ->pluck('id')
                ->all();

            foreach ($sale->items->where('line_type', 'item') as $item) {
                $item->item?->increment('qty', (float) $item->qty);
            }

            if ($itemLineIds !== []) {
                ItemLedger::query()
                    ->where('identifier', 'sale')
                    ->whereIn('foreign_key', $itemLineIds)
                    ->delete();
            }

            $sale->forceFill(['status' => Sale::STATUS_CANCELLED])->save();
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Sale cancelled successfully.');
    }
}
