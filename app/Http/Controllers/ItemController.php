<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateItemRequest;
use App\Models\BillLineItem;
use App\Http\Requests\StoreItemRequest;
use App\Models\Item;
use App\Models\ItemLedger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ItemController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Item::class);

        $items = Item::query()
            ->latest()
            ->paginate(20);

        return view('items.index', [
            'items' => $items,
        ]);
    }

    public function edit(Item $item): View
    {
        $this->authorize('update', $item);

        return view('items.edit', [
            'item' => $item,
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        $validated = $request->validated();

        Item::query()->create([
            'name' => $validated['name'],
            'qty' => (float) ($validated['qty'] ?? 0),
            'rate' => (float) $validated['rate'],
            'cost_price' => (float) $validated['cost_price'],
        ]);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item created successfully.');
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validated();

        $item->update([
            'name' => $validated['name'],
            'qty' => (float) $validated['qty'],
            'rate' => (float) $validated['rate'],
            'cost_price' => (float) $validated['cost_price'],
        ]);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);

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
