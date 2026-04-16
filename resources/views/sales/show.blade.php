@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Sale Bill</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $sale->party->name }} • AD {{ $sale->created_at->format('d M Y, h:i A') }}</p>
                    <p class="mt-1 text-sm text-gray-400">BS {{ $sale->created_at_bs }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $sale->isCancelled() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ $sale->isCancelled() ? 'Cancelled' : 'Active' }}
                    </span>
                    <p class="font-mono text-2xl font-semibold text-indigo-700">{{ number_format($sale->total, 2) }}</p>
                    @if (! $sale->isCancelled() && $sale->remaining_amount > 0)
                        <a href="{{ route('payments.create', ['party_id' => $sale->party_id, 'sale_id' => $sale->id]) }}" class="mt-3 inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Add Payment</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[640px] w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Particular</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Rate</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->items as $item)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-3">
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-600">{{ $item->line_type }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $item->line_label }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) ($item->qty ?? 0), 4) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $item->rate, 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">{{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right font-semibold text-gray-700">Total</td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-semibold text-indigo-700">{{ number_format($sale->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Linked Payments</h2>
                <span class="text-sm text-gray-500">{{ $linkedPayments->total() }} payment(s)</span>
            </div>
            <div class="space-y-3">
                @forelse ($linkedPayments as $payment)
                    <div class="flex flex-col gap-2 rounded-lg border border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-gray-900">{{ ucfirst($payment->type) }} via {{ $payment->account->name }}</p>
                            <p class="text-sm text-gray-500">AD {{ $payment->created_at->format('d M Y, h:i A') }}</p>
                            <p class="text-xs text-gray-500">Cheque: {{ $payment->cheque_number ?: '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono font-semibold text-indigo-700">{{ number_format($payment->amount, 2) }}</p>
                            <a href="{{ route('payments.show', $payment) }}" class="text-sm text-indigo-600">View</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No payments linked to this sale yet.</p>
                @endforelse
            </div>

            @if ($linkedPayments->hasPages())
                <div class="mt-4">
                    {{ $linkedPayments->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
