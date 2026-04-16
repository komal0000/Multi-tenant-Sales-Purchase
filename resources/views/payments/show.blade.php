@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Payment Detail</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $payment->party->name }} • {{ $payment->created_at->format('d M Y, h:i A') }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $payment->type === 'received' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ ucfirst($payment->type) }}
                </span>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-4">
                    <dt class="text-sm text-gray-500">Amount</dt>
                    <dd class="mt-1 font-mono text-xl font-semibold text-indigo-700">{{ number_format($payment->amount, 2) }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <dt class="text-sm text-gray-500">Account</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $payment->account->name }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <dt class="text-sm text-gray-500">Cheque Number</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $payment->cheque_number ?: '-' }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <dt class="text-sm text-gray-500">Notes</dt>
                    <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $payment->notes ?: '-' }}</dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 sm:col-span-2">
                    <dt class="text-sm text-gray-500">Linked Bill</dt>
                    <dd class="mt-1 text-gray-900">
                        @if ($payment->sale)
                            Sale #{{ $payment->sale->id }} / {{ $payment->sale->party->name ?? $payment->party->name }} / {{ number_format($payment->sale->total, 2) }}
                        @elseif ($payment->purchase)
                            Purchase #{{ $payment->purchase->id }} / {{ $payment->purchase->party->name ?? $payment->party->name }} / {{ number_format($payment->purchase->total, 2) }}
                        @else
                            Advance payment
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
