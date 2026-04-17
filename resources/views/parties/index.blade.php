@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Parties</h1>
                <p class="text-sm text-gray-500">Track receivable and payable balances from the ledger.</p>
            </div>
            <button
                type="button"
                data-open-quick-party-entry
                data-party-post-save="reload"
                class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
            >
                + Quick Add
            </button>
        </div>

        <form method="GET" action="{{ route('parties.index') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Search Parties</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Name, phone or address" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div class="flex items-center gap-3 md:pb-0.5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('parties.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-4 text-left">Name</th>
                            <th class="px-5 py-4 text-left">Phone</th>
                            <th class="px-5 py-4 text-left">Address</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="party-table-body">
                        @forelse ($parties as $party)
                            <tr data-party-row class="border-t border-gray-100 hover:bg-gray-50/80">
                                <td class="px-5 py-4 font-medium text-gray-900">
                                    <a href="{{ route('parties.show', $party) }}" class="hover:text-indigo-600">{{ $party->name }}</a>
                                </td>
                                <td class="px-5 py-4 text-gray-500">{{ $party->phone ?: '-' }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ $party->address ?: '-' }}</td>
                                <td class="px-5 py-4 text-right font-mono font-semibold {{ $party->balance > 0 ? 'text-green-600' : ($party->balance < 0 ? 'text-red-500' : 'text-gray-500') }}">
                                    {{ number_format(abs($party->balance), 2) }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="{{ route('parties.ledger', $party) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Ledger</a>
                                        <form action="{{ route('parties.destroy', $party) }}" method="POST" onsubmit="return confirm('Delete this party? Existing related records may prevent deletion.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty-row>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-500">No parties created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3 md:hidden">
            @forelse ($parties as $party)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('parties.show', $party) }}" class="font-semibold text-gray-900">{{ $party->name }}</a>
                            <p class="mt-1 text-sm text-gray-500">{{ $party->phone ?: 'No phone number' }}</p>
                            <p class="text-xs text-gray-500">{{ $party->address ?: 'No address' }}</p>
                        </div>
                        <span class="font-mono text-sm font-semibold {{ $party->balance > 0 ? 'text-green-600' : ($party->balance < 0 ? 'text-red-500' : 'text-gray-500') }}">
                            {{ number_format(abs($party->balance), 2) }}
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
                        <a href="{{ route('parties.ledger', $party) }}" class="text-sm font-medium text-indigo-600">View Ledger</a>
                        <form action="{{ route('parties.destroy', $party) }}" method="POST" onsubmit="return confirm('Delete this party? Existing related records may prevent deletion.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-red-500">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                    No parties created yet.
                </div>
            @endforelse
        </div>

        {{ $parties->links() }}
    </div>
@endsection
