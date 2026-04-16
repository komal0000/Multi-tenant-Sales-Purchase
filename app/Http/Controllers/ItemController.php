<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\BillLineItem;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Services\LedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Item::class);
        $this->ledger->ensureCompatibilitySchema();

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $items = Item::query()
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $term = '%'.trim((string) $keyword).'%';

                $query->where('name', 'like', $term);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('items.index', [
            'items' => $items,
            'filters' => [
                'keyword' => $filters['keyword'] ?? null,
            ],
        ]);
    }

    public function edit(Item $item): View
    {
        $this->authorize('update', $item);
        $this->ledger->ensureCompatibilitySchema();

        return view('items.edit', [
            'item' => $item,
            'openingQty' => (float) (ItemLedger::query()
                ->where('item_id', $item->id)
                ->where('identifier', 'opening_stock')
                ->value('qty') ?? 0),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $this->authorize('create', Item::class);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();

        $openingQty = (float) ($validated['qty'] ?? 0);

        $item = Item::query()->create([
            'name' => $validated['name'],
            'qty' => 0,
            'rate' => (float) $validated['rate'],
            'cost_price' => (float) $validated['cost_price'],
        ]);
        $this->ledger->syncItemOpeningStock($item, $openingQty);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item created successfully.');
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);
        $this->ledger->ensureCompatibilitySchema();

        $validated = $request->validated();
        $openingQty = (float) $validated['qty'];

        $item->update([
            'name' => $validated['name'],
            'rate' => (float) $validated['rate'],
            'cost_price' => (float) $validated['cost_price'],
        ]);
        $this->ledger->syncItemOpeningStock($item->fresh(), $openingQty);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);
        $this->ledger->ensureCompatibilitySchema();

        if (ItemLedger::query()->where('item_id', $item->id)->exists()) {
            return redirect()
                ->route('items.index')
                ->with('error', 'This item has stock ledger history and cannot be deleted.');
        }

        if (BillLineItem::query()->where('item_id', $item->id)->exists()) {
            return redirect()
                ->route('items.index')
                ->with('error', 'This item is used in bills and cannot be deleted.');
        }

        $item->delete();

        return redirect()
            ->route('items.index')
            ->with('success', 'Item deleted successfully.');
    }
}
