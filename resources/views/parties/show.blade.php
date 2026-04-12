@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $party->name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Phone: {{ $party->phone ?: 'Not provided' }}</p>
                    <p class="text-sm text-gray-500">Address: {{ $party->address ?: 'Not provided' }}</p>
                </div>
                @include('partials.balance-badge', ['balance' => $balance])
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Sales</p>
                    <p class="mt-1 text-xl font-semibold">{{ $party->sales_count }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Purchases</p>
                    <p class="mt-1 text-xl font-semibold">{{ $party->purchases_count }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Payments</p>
                    <p class="mt-1 text-xl font-semibold">{{ $party->payments_count }}</p>
                </div>
            </div>
            <div class="mt-4 rounded-lg bg-gray-50 p-4">
                <p class="text-sm text-gray-500">Opening Balance</p>
                <p class="mt-1 text-xl font-semibold {{ $openingBalanceSigned >= 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ number_format(abs($openingBalanceSigned), 2) }} {{ $openingBalanceSigned >= 0 ? 'DR' : 'CR' }}
                </p>
            </div>
            <form method="POST" action="{{ route('parties.opening-balance.update', $party) }}" class="mt-4 rounded-lg border border-gray-200 p-4">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold text-gray-800">Manage Opening Balance</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="opening_balance" class="block text-xs font-medium text-gray-600">Amount</label>
                        <input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" value="{{ old('opening_balance', $party->opening_balance ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="opening_balance_side" class="block text-xs font-medium text-gray-600">Side</label>
                        <select id="opening_balance_side" name="opening_balance_side" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="dr" @selected(old('opening_balance_side', $party->opening_balance_side ?? 'dr') === 'dr')>DR</option>
                            <option value="cr" @selected(old('opening_balance_side', $party->opening_balance_side ?? 'dr') === 'cr')>CR</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Opening Balance</button>
            </form>
            <div class="mt-6">
                <a href="{{ route('parties.ledger', $party) }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">View Ledger Statement</a>
            </div>
        </div>
    </div>
@endsection

