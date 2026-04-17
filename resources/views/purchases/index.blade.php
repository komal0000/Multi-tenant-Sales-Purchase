@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Purchases</h1>
                <p class="text-sm text-gray-500">Search purchases by Nepali date range and party.</p>
            </div>
            <a href="{{ route('purchases.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">New Purchase</a>
        </div>

        <form method="GET" action="{{ route('purchases.index') }}" class="sales-purchase-search-grid rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div>
                <div class="flex items-center justify-between">
                    <label for="purchases-filter-party-select" class="block text-sm font-medium text-gray-700">Party</label>
                    <button type="button" data-open-quick-party-entry data-party-select-id="purchases-filter-party-select" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Quick Add</button>
                </div>
                <select id="purchases-filter-party-select" name="party_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All parties</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected(($filters['party_id'] ?? null) === $party->id)>{{ $party->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
            @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
            <div>
                <label class="block text-sm font-medium text-gray-700">Keyword</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Bill no, amount, party" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
            </div>
            <div class="flex flex-wrap items-center gap-3 md:pb-0.5">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="show_cancelled" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked($filters['show_cancelled'] ?? false)>
                    <span>Show cancelled bills</span>
                </label>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('purchases.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-4 text-left">Party</th>
                            <th class="px-5 py-4 text-right">Total</th>
                            <th class="px-5 py-4 text-right">Paid</th>
                            <th class="px-5 py-4 text-left">AD Date</th>
                            <th class="px-5 py-4 text-left">BS Date</th>
                            <th class="px-5 py-4 text-left">Status</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchases as $purchase)
                            <tr class="border-t border-gray-100 hover:bg-gray-50/80">
                                <td class="px-5 py-4 font-medium text-gray-900"><a href="{{ route('purchases.show', $purchase) }}" class="hover:text-indigo-600">{{ $purchase->party->name }}</a></td>
                                <td class="px-5 py-4 text-right font-mono font-semibold text-indigo-700">{{ number_format($purchase->total, 2) }}</td>
                                <td class="px-5 py-4 text-right font-mono {{ $purchase->paid_amount > 0 ? 'text-green-600' : 'text-gray-500' }}">{{ number_format($purchase->paid_amount, 2) }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ $purchase->created_at->format('d M Y') }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ $purchase->created_at_bs }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $purchase->isCancelled() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $purchase->isCancelled() ? 'Cancelled' : 'Active' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="{{ route('purchases.show', $purchase) }}" class="text-sm text-indigo-600 hover:text-indigo-700">View</a>
                                        @if (! $purchase->isCancelled())
                                            <a href="{{ route('payments.create', ['party_id' => $purchase->party_id, 'purchase_id' => $purchase->id]) }}" class="text-sm text-green-600 hover:text-green-700">Payment</a>
                                            <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" onsubmit="return confirm('Cancel this purchase? This will remove its ledger and stock impact.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-500 hover:text-red-700">Cancel</button>
                                            </form>
                                        @else
                                            <span class="text-sm text-gray-400">Locked</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-gray-500">
                                    {{ $hasSearched ? 'No purchases found.' : 'Use filters and click Search to load purchases.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3 md:hidden">
            @forelse ($purchases as $purchase)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('purchases.show', $purchase) }}" class="font-semibold text-gray-900">{{ $purchase->party->name }}</a>
                            <p class="mt-1 text-sm text-gray-500">AD {{ $purchase->created_at->format('d M Y') }}</p>
                            <p class="text-xs text-gray-400">BS {{ $purchase->created_at_bs }}</p>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-sm font-semibold text-indigo-700">{{ number_format($purchase->total, 2) }}</div>
                            <div class="mt-1 font-mono text-xs {{ $purchase->paid_amount > 0 ? 'text-green-600' : 'text-gray-500' }}">Paid {{ number_format($purchase->paid_amount, 2) }}</div>
                            <div class="mt-2 text-xs font-medium {{ $purchase->isCancelled() ? 'text-red-600' : 'text-emerald-600' }}">{{ $purchase->isCancelled() ? 'Cancelled' : 'Active' }}</div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
                        <a href="{{ route('purchases.show', $purchase) }}" class="font-medium text-indigo-600">View Bill</a>
                        @if (! $purchase->isCancelled())
                            <a href="{{ route('payments.create', ['party_id' => $purchase->party_id, 'purchase_id' => $purchase->id]) }}" class="font-medium text-green-600">Add Payment</a>
                        @else
                            <span class="font-medium text-gray-400">Locked</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                    {{ $hasSearched ? 'No purchases found.' : 'Use filters and tap Search to load purchases.' }}
                </div>
            @endforelse
        </div>

        @if ($hasSearched)
            {{ $purchases->links() }}
        @endif
    </div>
@endsection
