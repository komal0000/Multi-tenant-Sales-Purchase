@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Items</h1>
                <p class="text-sm text-gray-500">Add and manage stockable items used in sales and purchases.</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-800">Add New Item</h2>
            <form action="{{ route('items.store') }}" method="POST" class="mt-4 grid gap-3 md:grid-cols-12">
                @csrf
                <div class="md:col-span-4">
                    <label for="name" class="block text-xs font-medium text-gray-600">Item Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="qty" class="block text-xs font-medium text-gray-600">Opening Qty</label>
                    <input id="qty" name="qty" type="number" min="0" step="0.0001" value="{{ old('qty', '0') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right">
                </div>
                <div class="md:col-span-2">
                    <label for="rate" class="block text-xs font-medium text-gray-600">Sale Rate</label>
                    <input id="rate" name="rate" type="number" min="0" step="0.0001" value="{{ old('rate') }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right">
                </div>
                <div class="md:col-span-2">
                    <label for="cost_price" class="block text-xs font-medium text-gray-600">Cost Price</label>
                    <input id="cost_price" name="cost_price" type="number" min="0" step="0.0001" value="{{ old('cost_price') }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-right">
                </div>
                <div class="md:col-span-2 md:flex md:items-end">
                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Item</button>
                </div>
            </form>
        </div>

        <form method="GET" action="{{ route('items.index') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Search Items</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Item name" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
            </div>
            <div class="flex items-center gap-3 md:pb-0.5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('items.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Sale Rate</th>
                            <th class="px-4 py-3 text-right">Cost Price</th>
                            <th class="px-4 py-3 text-left">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->name }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $item->qty, 4) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $item->rate, 4) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $item->cost_price, 4) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $item->created_at?->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('items.edit', $item) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Edit</a>
                                        <form action="{{ route('items.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this item?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">No items created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $items->links() }}
    </div>
@endsection
