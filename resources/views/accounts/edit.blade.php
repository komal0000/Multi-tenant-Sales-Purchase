@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-900">Edit Account</h1>
        <p class="mt-1 text-sm text-gray-500">Update the account name and, if unused, the account type.</p>

        <form action="{{ route('accounts.update', $account) }}" method="POST" class="mt-6 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $account->name) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                @unless ($canChangeType)
                    <input type="hidden" name="type" value="{{ $account->type }}">
                @endunless
                <select id="type" name="type" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" @disabled(! $canChangeType)>
                    <option value="cash" @selected(old('type', $account->type) === 'cash')>Cash</option>
                    <option value="bank" @selected(old('type', $account->type) === 'bank')>Bank</option>
                </select>
                @unless ($canChangeType)
                    <p class="mt-2 text-xs text-amber-600">Account type is locked because this account already has usage history.</p>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('accounts.show', $account) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Account</button>
            </div>
        </form>
    </div>
@endsection
