@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-900">Create Account</h1>
        <form action="{{ route('accounts.store') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                <select id="type" name="type" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="cash" @selected(old('type') === 'cash')>Cash</option>
                    <option value="bank" @selected(old('type') === 'bank')>Bank</option>
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="opening_balance" class="block text-sm font-medium text-gray-700">Opening Balance</label>
                    <input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" value="{{ old('opening_balance', '0') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                </div>
                <div>
                    <label for="opening_balance_side" class="block text-sm font-medium text-gray-700">Balance Type</label>
                    <select id="opening_balance_side" name="opening_balance_side" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="dr" @selected(old('opening_balance_side', 'dr') === 'dr')>Receivable</option>
                        <option value="cr" @selected(old('opening_balance_side') === 'cr')>Payable</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    @include('partials.bs-date-selector', ['name' => 'opening_balance_date_bs', 'label' => 'Opening BS Date', 'value' => old('opening_balance_date_bs', \App\Helpers\DateHelper::getCurrentBS())])
                </div>
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('accounts.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Account</button>
            </div>
        </form>
    </div>
@endsection



