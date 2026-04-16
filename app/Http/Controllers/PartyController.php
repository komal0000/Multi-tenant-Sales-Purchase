<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyOpeningBalanceRequest;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Party;
use App\Services\LedgerService;
use App\Services\PartyCacheService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PartyController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PartyCacheService $partyCache,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Party::class);
        $this->ledger->ensureCompatibilitySchema();

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $parties = Party::query()
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $term = '%'.trim((string) $keyword).'%';

                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('address', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $partyIds = $parties->getCollection()->pluck('id')->all();
        $ledgerBalances = empty($partyIds)
            ? collect()
            : Ledger::query()
                ->whereIn('party_id', $partyIds)
                ->selectRaw('party_id, COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
                ->groupBy('party_id')
                ->pluck('balance', 'party_id');

        $parties->setCollection(
            $parties->getCollection()->map(function (Party $party) use ($ledgerBalances) {
                $party->balance = (float) ($ledgerBalances[$party->id] ?? 0);

                return $party;
            })
        );

        return view('parties.index', [
            'parties' => $parties,
            'filters' => [
                'keyword' => $filters['keyword'] ?? null,
            ],
        ]);
    }

    public function store(StorePartyRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Party::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();

        $validated['opening_balance'] = (float) ($validated['opening_balance'] ?? 0);
        $validated['opening_balance_side'] = $validated['opening_balance_side'] ?? 'dr';
        $validated['opening_balance_date'] = filled($validated['opening_balance_date_bs'] ?? null)
            ? DateHelper::toDateInt((string) $validated['opening_balance_date_bs'])
            : DateHelper::currentBsInt();
        unset($validated['opening_balance_date_bs']);

        $party = Party::query()->create($validated);
        $this->ledger->syncPartyOpeningBalance($party);

        $this->partyCache->refreshAll();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Party created successfully.',
                'party' => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'phone' => $party->phone,
                    'address' => $party->address,
                    'opening_balance' => (float) ($party->opening_balance ?? 0),
                    'opening_balance_side' => $party->opening_balance_side,
                    'opening_balance_date' => (int) ($party->opening_balance_date ?? DateHelper::currentBsInt()),
                ],
            ], 201);
        }

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Party created successfully.');
    }

    public function show(Party $party): View
    {
        $this->authorize('view', $party);
        $this->ledger->ensureCompatibilitySchema();

        return view('parties.show', [
            'party' => $party->loadCount(['sales', 'purchases', 'payments']),
            'balance' => $this->ledger->partyBalance($party->id),
            'openingBalanceSigned' => $this->openingSigned((float) ($party->opening_balance ?? 0), $party->opening_balance_side ?? 'dr'),
        ]);
    }

    public function ledgerStatement(Request $request, Party $party): View
    {
        $this->authorize('view', $party);
        $this->ledger->ensureCompatibilitySchema();

        $filters = $request->validate([
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

        $query = Ledger::query()->where('party_id', $party->id);

        $openingBalance = ((clone $query)
            ->when($fromBsInt, fn ($builder) => $builder->where('date', '<', $fromBsInt))
            ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->value('balance') ?? 0);

        $ledgerRows = (clone $query)
            ->when($fromBsInt, fn ($builder) => $builder->where('date', '>=', $fromBsInt))
            ->when($toBsInt, fn ($builder) => $builder->where('date', '<=', $toBsInt))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $this->ledger->attachReferenceText($ledgerRows);

        $accounts = Account::query()
            ->orderByRaw("case when type = 'cash' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return view('parties.ledger', [
            'party' => $party,
            'ledgerRows' => $ledgerRows,
            'openingBalance' => (float) $openingBalance,
            'currentBalance' => $this->ledger->partyBalance($party->id),
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'defaultCashAccountId' => $accounts->firstWhere('type', 'cash')?->id,
            'filters' => [
                'from_date_bs' => $filters['from_date_bs'] ?? null,
                'to_date_bs' => $filters['to_date_bs'] ?? null,
            ],
        ]);
    }

    public function updateOpeningBalance(UpdatePartyOpeningBalanceRequest $request, Party $party): RedirectResponse
    {
        $this->authorize('update', $party);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();

        $party->update([
            'opening_balance' => (float) $validated['opening_balance'],
            'opening_balance_side' => $validated['opening_balance_side'],
            'opening_balance_date' => filled($validated['opening_balance_date_bs'] ?? null)
                ? DateHelper::toDateInt((string) $validated['opening_balance_date_bs'])
                : (int) ($party->opening_balance_date ?? DateHelper::currentBsInt()),
        ]);
        $this->ledger->syncPartyOpeningBalance($party);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Opening balance updated successfully.');
    }

    private function openingSigned(float $amount, string $side): float
    {
        return $side === 'cr' ? -$amount : $amount;
    }

    public function destroy(Party $party): RedirectResponse
    {
        $this->authorize('delete', $party);
        $this->ledger->ensureCompatibilitySchema();

        $party->delete();

        $this->partyCache->refreshAll();

        return redirect()
            ->route('parties.index')
            ->with('success', 'Party deleted successfully.');
    }
}
