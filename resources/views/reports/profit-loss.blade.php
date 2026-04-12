@extends('layouts.app')

@section('content')
    <div class="space-y-6" x-data="{ tab: 'total' }">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Profit / Loss</h1>
            <p class="text-sm text-gray-500">Summary for a selected date range.</p>
        </div>

        <form method="GET" action="{{ route('reports.profit-loss') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
                </div>
                <div>
                    @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
                    <a href="{{ route('reports.profit-loss') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </div>
        </form>

        <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
            <button type="button" @click="tab = 'total'" class="px-4 py-2 text-sm font-medium" :class="tab === 'total' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Total</button>
            <button type="button" @click="tab = 'detail'" class="px-4 py-2 text-sm font-medium" :class="tab === 'detail' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Detail</button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3" x-show="tab === 'total'" x-cloak>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total Sales</p>
                <p class="mt-2 font-mono text-2xl font-semibold text-green-600">{{ number_format($salesTotal, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total Purchases</p>
                <p class="mt-2 font-mono text-2xl font-semibold text-red-500">{{ number_format($purchaseTotal, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Result</p>
                <p class="mt-2 font-mono text-2xl font-semibold {{ $profitLoss >= 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ number_format(abs($profitLoss), 2) }}
                </p>
                <p class="mt-1 text-xs font-semibold {{ $profitLoss >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    {{ $profitLoss >= 0 ? 'Profit' : 'Loss' }}
                </p>
            </div>
        </div>

        @if (! $hasSearched)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Use date filters and click Apply to generate the Profit and Loss report.
            </div>
        @endif

        <div class="space-y-4" x-show="tab === 'detail'" x-cloak>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-800">Profit and Loss Statement (Detail)</h2>
                </div>
                <div class="overflow-x-auto">
                    @php
                        $lineCount = max($salesDetails->count(), $purchaseDetails->count());
                    @endphp
                    <table class="min-w-[920px] w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                            <tr>
                                <th colspan="2" class="px-4 py-3 text-left text-green-700">Income</th>
                                <th colspan="2" class="px-4 py-3 text-left text-red-700">Expenses</th>
                            </tr>
                            <tr>
                                <th class="px-4 py-2 text-left">Particular</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-left">Particular</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < $lineCount; $i++)
                                @php
                                    $income = $salesDetails[$i] ?? null;
                                    $expense = $purchaseDetails[$i] ?? null;
                                @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2 text-gray-700">
                                        @if ($income)
                                            Sale - {{ $income->party?->name ?? 'Unknown Party' }} ({{ $income->created_at_bs }})
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-green-700">
                                        {{ $income ? number_format((float) $income->total, 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        @if ($expense)
                                            Purchase - {{ $expense->party?->name ?? 'Unknown Party' }} ({{ $expense->created_at_bs }})
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-red-700">
                                        {{ $expense ? number_format((float) $expense->total, 2) : '-' }}
                                    </td>
                                </tr>
                            @endfor

                            @if ($lineCount === 0)
                                <tr class="border-t border-gray-100">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No rows for selected date range.</td>
                                </tr>
                            @endif

                            <tr class="border-t-2 border-gray-300 bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-green-800">Total Income</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-green-800">{{ number_format($salesTotal, 2) }}</td>
                                <td class="px-4 py-3 font-semibold text-red-800">Total Expenses</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-red-800">{{ number_format($purchaseTotal, 2) }}</td>
                            </tr>
                            <tr class="border-t border-gray-200 bg-gray-50">
                                <td colspan="3" class="px-4 py-3 font-semibold text-gray-700">Net {{ $profitLoss >= 0 ? 'Profit' : 'Loss' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $profitLoss >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format(abs($profitLoss), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
