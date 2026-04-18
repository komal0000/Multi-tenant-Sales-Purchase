@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $account->name }}</h1>
                    <p class="mt-1 text-sm capitalize text-gray-500">Type: {{ $account->type }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('accounts.ledger', $account) }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">View Ledger Statement</a>
                        @can('update', $account)
                            <a href="{{ route('accounts.edit', $account) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit Account</a>
                        @endcan
                    </div>
                </div>
                @include('partials.balance-badge', ['balance' => $balance])
            </div>
            <div class="mt-6 rounded-lg bg-gray-50 p-4">
                <p class="text-sm text-gray-500">Payments Recorded</p>
                <p class="mt-1 text-xl font-semibold">{{ $account->payments_count }}</p>
            </div>
            <div class="mt-4 rounded-lg bg-gray-50 p-4">
                <p class="text-sm text-gray-500">Opening Balance</p>
                <p class="mt-1 text-xl font-semibold {{ $openingBalanceSigned >= 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ number_format(abs($openingBalanceSigned), 2) }} {{ $openingBalanceSigned >= 0 ? 'Receivable' : 'Payable' }}
                </p>
            </div>
            <form method="POST" action="{{ route('accounts.opening-balance.update', $account) }}" class="mt-4 rounded-lg border border-gray-200 p-4">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold text-gray-800">Manage Opening Balance</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="opening_balance" class="block text-xs font-medium text-gray-600">Amount</label>
                        <input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" value="{{ old('opening_balance', $account->opening_balance ?? 0) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    </div>
                    <div>
                        <label for="opening_balance_side" class="block text-xs font-medium text-gray-600">Balance Type</label>
                        <select id="opening_balance_side" name="opening_balance_side" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                            <option value="dr" @selected(old('opening_balance_side', $account->opening_balance_side ?? 'dr') === 'dr')>Receivable</option>
                            <option value="cr" @selected(old('opening_balance_side', $account->opening_balance_side ?? 'dr') === 'cr')>Payable</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        @include('partials.bs-date-selector', ['name' => 'opening_balance_date_bs', 'label' => 'Opening BS Date', 'value' => old('opening_balance_date_bs', \App\Helpers\DateHelper::fromDateInt((int) ($account->opening_balance_date ?? \App\Helpers\DateHelper::currentBsInt())))])
                    </div>
                </div>
                <button type="submit" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Opening Balance</button>
            </form>
        </div>
    </div>
@endsection
