@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Accounts</h1>
                <p class="text-sm text-gray-500">Cash and bank balances derived from the ledger.</p>
            </div>
            <a href="{{ route('accounts.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">New Account</a>
        </div>

        <form method="GET" action="{{ route('accounts.index') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Search Accounts</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Account name or type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div class="flex items-center gap-3 md:pb-0.5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('accounts.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-4 text-left">Name</th>
                            <th class="px-5 py-4 text-left">Type</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr class="border-t border-gray-100 hover:bg-gray-50/80">
                                <td class="px-5 py-4 font-medium text-gray-900">
                                    <a href="{{ route('accounts.show', $account) }}" class="hover:text-indigo-600">{{ $account->name }}</a>
                                </td>
                                <td class="px-5 py-4 capitalize text-gray-500">{{ $account->type }}</td>
                                <td class="px-5 py-4 text-right font-mono font-semibold {{ $account->balance >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                    {{ number_format(abs($account->balance), 2) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('accounts.ledger', $account) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Ledger</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-500">No accounts created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3 md:hidden">
            @forelse ($accounts as $account)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('accounts.show', $account) }}" class="font-semibold text-gray-900">{{ $account->name }}</a>
                            <p class="mt-1 text-sm capitalize text-gray-500">{{ $account->type }}</p>
                        </div>
                        <span class="font-mono text-sm font-semibold {{ $account->balance >= 0 ? 'text-green-600' : 'text-red-500' }}">
                            {{ number_format(abs($account->balance), 2) }}
                        </span>
                    </div>
                    <div class="mt-4 border-t border-gray-100 pt-3">
                        <a href="{{ route('accounts.ledger', $account) }}" class="text-sm font-medium text-indigo-600">View Ledger</a>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                    No accounts created yet.
                </div>
            @endforelse
        </div>

        {{ $accounts->links() }}
    </div>
@endsection
