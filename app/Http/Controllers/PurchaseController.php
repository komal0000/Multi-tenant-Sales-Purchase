<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Payment;
use App\Models\Purchase;
use App\Services\LedgerService;
use App\Services\PartyCacheService;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $service,
        private readonly PartyCacheService $partyCache,
        private readonly LedgerService $ledger,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Purchase::class);
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

            $purchases = Purchase::query()
                ->with(['party', 'payments'])
                ->when(! $showCancelled, fn ($query) => $query->where('status', Purchase::STATUS_ACTIVE))
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

            $purchases->through(function (Purchase $purchase) {
                $purchase->created_at_bs = DateHelper::fromDateInt((int) $purchase->date);
                $purchase->paid_amount = (float) $purchase->payments->where('type', 'given')->sum('amount');

                return $purchase;
            });
        } else {
            $purchases = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: 20,
                currentPage: 1,
                options: ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('purchases.index', [
            'purchases' => $purchases,
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
        $this->authorize('create', Purchase::class);
        $this->ledger->ensureCompatibilitySchema();

        $accounts = Account::query()
            ->orderByRaw("case when type = 'cash' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return view('purchases.create', [
            'parties' => $this->partyCache->all(),
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'accountsCreateUrl' => route('accounts.create'),
            'itemsCatalog' => Item::query()->orderBy('name')->get(['id', 'name', 'qty', 'rate', 'cost_price']),
            'expenseCategories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'defaultCashAccountId' => $accounts->firstWhere('type', 'cash')?->id,
            'currentBsDate' => DateHelper::getCurrentBS(),
            'currentBsDateInt' => DateHelper::currentBsInt(),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $this->authorize('create', Purchase::class);
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

        $purchase = $this->service->create($validated);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Purchase created successfully.');
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);
        $this->ledger->ensureCompatibilitySchema();

        $purchase->load(['party', 'items.item', 'items.expenseCategory']);
        $purchase->created_at_bs = DateHelper::fromDateInt((int) $purchase->date);
        $purchase->paid_amount = (float) $purchase->payments()->where('type', 'given')->sum('amount');
        $purchase->remaining_amount = max(0, (float) $purchase->total - $purchase->paid_amount);

        $linkedPayments = $purchase->payments()
            ->with('account')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchases.show', [
            'purchase' => $purchase,
            'linkedPayments' => $linkedPayments,
        ]);
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);
        $this->ledger->ensureCompatibilitySchema();

        DB::transaction(function () use ($purchase): void {
            if ($purchase->status === Purchase::STATUS_CANCELLED) {
                return;
            }

            $purchase->loadMissing([
                'payments' => fn ($query) => $query->withTrashed(),
                'items.item',
            ]);

            $paymentIds = $purchase->payments->pluck('id')->all();

            $this->ledger->removeEntries('purchases', $purchase->id);
            $this->ledger->removeEntries('payments', $paymentIds);

            if ($paymentIds !== []) {
                Payment::withTrashed()
                    ->whereIn('id', $paymentIds)
                    ->delete();
            }

            $itemLineIds = $purchase->items
                ->where('line_type', 'item')
                ->pluck('id')
                ->all();

            foreach ($purchase->items->where('line_type', 'item') as $item) {
                $item->item?->decrement('qty', (float) $item->qty);
            }

            if ($itemLineIds !== []) {
                ItemLedger::query()
                    ->where('identifier', 'purchase')
                    ->whereIn('foreign_key', $itemLineIds)
                    ->delete();
            }

            $purchase->forceFill(['status' => Purchase::STATUS_CANCELLED])->save();
        });

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Purchase cancelled successfully.');
    }
}
