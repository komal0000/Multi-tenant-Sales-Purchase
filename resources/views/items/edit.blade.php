@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-900">Edit Item</h1>
        <p class="mt-1 text-sm text-gray-500">Update item details used in sales and purchases.</p>

        <form action="{{ route('items.update', $item) }}" method="POST" class="mt-6 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Item Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $item->name) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="qty" class="block text-sm font-medium text-gray-700">Opening Qty</label>
                    <input id="qty" name="qty" type="number" min="0" step="0.0001" value="{{ old('qty', $openingQty) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-right">
                </div>
                <div>
                    <label for="rate" class="block text-sm font-medium text-gray-700">Sale Rate</label>
                    <input id="rate" name="rate" type="number" min="0" step="0.0001" value="{{ old('rate', (float) $item->rate) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-right">
                </div>
                <div>
                    <label for="cost_price" class="block text-sm font-medium text-gray-700">Cost Price</label>
                    <input id="cost_price" name="cost_price" type="number" min="0" step="0.0001" value="{{ old('cost_price', (float) $item->cost_price) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-right">
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('items.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Item</button>
            </div>
        </form>
    </div>
@endsection
