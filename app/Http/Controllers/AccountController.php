<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\LedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Account::class);

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $accounts = Account::query()
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $term = '%'.trim((string) $keyword).'%';

                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('name', 'like', $term)
                        ->orWhere('type', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $accountIds = $accounts->getCollection()->pluck('id')->all();
        $ledgerBalances = empty($accountIds)
            ? collect()
            : Ledger::query()
                ->whereIn('account_id', $accountIds)
                ->selectRaw('account_id, COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
                ->groupBy('account_id')
                ->pluck('balance', 'account_id');

        $accounts->setCollection(
            $accounts->getCollection()->map(function (Account $account) use ($ledgerBalances) {
                $opening = (float) ($account->opening_balance ?? 0);
                $openingSigned = ($account->opening_balance_side ?? 'dr') === 'cr' ? -$opening : $opening;
                $account->balance = (float) ($ledgerBalances[$account->id] ?? 0) + $openingSigned;

                return $account;
            })
        );

        return view('accounts.index', [
            'accounts' => $accounts,
            'filters' => [
                'keyword' => $filters['keyword'] ?? null,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Account::class);

        return view('accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,bank'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_side' => ['nullable', 'in:dr,cr'],
        ]);

        $validated['opening_balance'] = (float) ($validated['opening_balance'] ?? 0);
        $validated['opening_balance_side'] = $validated['opening_balance_side'] ?? 'dr';

        $account = Account::query()->create($validated);

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Account created successfully.');
    }

    public function show(Account $account): View
    {
        $this->authorize('view', $account);

        return view('accounts.show', [
            'account' => $account->loadCount('payments'),
            'balance' => $this->ledger->accountBalance($account->id),
            'openingBalanceSigned' => $this->openingSigned((float) ($account->opening_balance ?? 0), $account->opening_balance_side ?? 'dr'),
        ]);
    }

    public function ledgerStatement(Request $request, Account $account): View
    {
        $this->authorize('view', $account);

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

        $query = Ledger::query()->where('account_id', $account->id);

        $openingBase = $this->openingSigned((float) ($account->opening_balance ?? 0), $account->opening_balance_side ?? 'dr');

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

        $this->attachReferenceText($ledgerRows);

        return view('accounts.ledger', [
            'account' => $account,
            'ledgerRows' => $ledgerRows,
            'openingBalance' => (float) $openingBalance,
            'filters' => [
                'from_date_bs' => $filters['from_date_bs'] ?? null,
                'to_date_bs' => $filters['to_date_bs'] ?? null,
            ],
        ]);
    }

    public function updateOpeningBalance(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_side' => ['required', 'in:dr,cr'],
        ]);

        $account->update([
            'opening_balance' => (float) $validated['opening_balance'],
            'opening_balance_side' => $validated['opening_balance_side'],
        ]);

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Opening balance updated successfully.');
    }

    private function attachReferenceText(Collection $ledgerRows): void
    {
        $saleMap = Sale::query()
            ->with(['items.item:id,name', 'items.expenseCategory:id,name'])
            ->whereIn('id', $ledgerRows->where('ref_table', 'sales')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        $purchaseMap = Purchase::query()
            ->with(['items.item:id,name', 'items.expenseCategory:id,name'])
            ->whereIn('id', $ledgerRows->where('ref_table', 'purchases')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        $paymentMap = Payment::query()
            ->with('party:id,name')
            ->whereIn('id', $ledgerRows->where('ref_table', 'payments')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($ledgerRows as $row) {
            if ($row->ref_table === 'sales') {
                $sale = $saleMap->get($row->ref_id);
                $row->reference_text = $sale
                    ? 'Sale / '.$this->itemSummary($sale->items)
                    : 'Sale / '.$row->ref_id;

                continue;
            }

            if ($row->ref_table === 'purchases') {
                $purchase = $purchaseMap->get($row->ref_id);
                $row->reference_text = $purchase
                    ? 'Purchase / '.$this->itemSummary($purchase->items)
                    : 'Purchase / '.$row->ref_id;

                continue;
            }

            if ($row->ref_table === 'payments') {
                $payment = $paymentMap->get($row->ref_id);
                $row->reference_text = $payment
                    ? 'Payment / '.($payment->party?->name ?? 'Unknown Party')
                    : 'Payment / '.$row->ref_id;

                continue;
            }

            $row->reference_text = ucfirst($row->ref_table).' / '.$row->ref_id;
        }
    }

    private function itemSummary(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'No items';
        }

        return $items
            ->map(fn ($item) => sprintf(
                '%s @ %s * %s',
                $item->line_label,
                number_format((float) $item->rate, 2),
                number_format((float) $item->qty, 2)
            ))
            ->implode(', ');
    }

    private function openingSigned(float $amount, string $side): float
    {
        return $side === 'cr' ? -$amount : $amount;
    }
}
