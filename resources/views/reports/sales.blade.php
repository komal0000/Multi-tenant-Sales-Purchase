@extends('layouts.app')

@section('content')
    <div class="space-y-6" x-data="{ tab: 'summary' }">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Sales Report</h1>
            <p class="text-sm text-gray-500">Summary, date wise, party wise, and date-party wise sales breakdowns.</p>
        </div>

        <form method="GET" action="{{ route('reports.sales') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="sales-purchase-report-filter-grid">
                <div>
                    <label for="sales-report-party-filter" class="block text-sm font-medium text-gray-700">Party</label>
                    <select id="sales-report-party-filter" name="party_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="">All parties</option>
                        @foreach ($parties as $party)
                            <option value="{{ $party->id }}" @selected((string) ($filters['party_id'] ?? '') === (string) $party->id)>{{ $party->name }}</option>
                        @endforeach
                    </select>
                </div>
                @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
                @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
                <div class="flex items-center gap-2 md:pb-0.5">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
                    <a href="{{ route('reports.sales') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </div>
        </form>

        @if (! $hasSearched)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Use filters and click Apply to generate the Sales Report.
            </div>
        @else
            <div class="inline-flex flex-wrap overflow-hidden rounded-lg border border-gray-300">
                <button type="button" @click="tab = 'summary'" class="px-4 py-2 text-sm font-medium" :class="tab === 'summary' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Summary</button>
                <button type="button" @click="tab = 'date-wise'" class="px-4 py-2 text-sm font-medium" :class="tab === 'date-wise' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Date Wise</button>
                <button type="button" @click="tab = 'party-wise'" class="px-4 py-2 text-sm font-medium" :class="tab === 'party-wise' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Party Wise</button>
                <button type="button" @click="tab = 'date-party-wise'" class="px-4 py-2 text-sm font-medium" :class="tab === 'date-party-wise' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Date Party Wise</button>
            </div>

            <section class="space-y-4" x-show="tab === 'summary'" x-cloak>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Total Sales</p>
                        <p class="mt-2 font-mono text-xl font-semibold text-indigo-700">{{ number_format((float) $summary['total_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Total Bills</p>
                        <p class="mt-2 font-mono text-xl font-semibold text-gray-800">{{ number_format((float) $summary['bill_count'], 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Unique Parties</p>
                        <p class="mt-2 font-mono text-xl font-semibold text-gray-800">{{ number_format((float) $summary['party_count'], 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Average Bill</p>
                        <p class="mt-2 font-mono text-xl font-semibold text-green-600">{{ number_format((float) $summary['average_bill'], 2) }}</p>
                    </div>
                </div>

                @include('reports.partials.line-breakdowns', [
                    'generalized' => $summary['generalized'],
                    'itemWise' => $summary['item_wise'],
                    'includeExpense' => false,
                ])
            </section>

            <section class="space-y-3" x-show="tab === 'date-wise'" x-cloak>
                @forelse ($dateWiseRows as $dateRow)
                    <details class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $dateRow['date_bs'] }} <span class="text-xs text-gray-500">({{ $dateRow['date_ad'] }})</span></p>
                                <p class="text-xs text-gray-500">{{ number_format((float) $dateRow['bill_count'], 0) }} bills • {{ number_format((float) $dateRow['party_count'], 0) }} parties</p>
                            </div>
                            <p class="font-mono text-sm font-semibold text-indigo-700">{{ number_format((float) $dateRow['total_amount'], 2) }}</p>
                        </summary>
                        <div class="border-t border-gray-200 p-4">
                            @include('reports.partials.line-breakdowns', [
                                'generalized' => $dateRow['generalized'],
                                'itemWise' => $dateRow['item_wise'],
                                'includeExpense' => false,
                            ])
                        </div>
                    </details>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">No date-wise rows found.</div>
                @endforelse
            </section>

            <section class="space-y-3" x-show="tab === 'party-wise'" x-cloak>
                @forelse ($partyWiseRows as $partyRow)
                    <details class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $partyRow['party_name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $partyRow['party_phone'] ?: '-' }} • {{ number_format((float) $partyRow['bill_count'], 0) }} bills • {{ number_format((float) $partyRow['date_count'], 0) }} dates</p>
                            </div>
                            <p class="font-mono text-sm font-semibold text-indigo-700">{{ number_format((float) $partyRow['total_amount'], 2) }}</p>
                        </summary>
                        <div class="border-t border-gray-200 p-4">
                            @include('reports.partials.line-breakdowns', [
                                'generalized' => $partyRow['generalized'],
                                'itemWise' => $partyRow['item_wise'],
                                'includeExpense' => false,
                            ])
                        </div>
                    </details>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">No party-wise rows found.</div>
                @endforelse
            </section>

            <section class="space-y-3" x-show="tab === 'date-party-wise'" x-cloak>
                @forelse ($datePartyWiseRows as $dateRow)
                    <details class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $dateRow['date_bs'] }} <span class="text-xs text-gray-500">({{ $dateRow['date_ad'] }})</span></p>
                                <p class="text-xs text-gray-500">{{ number_format((float) $dateRow['bill_count'], 0) }} bills • {{ number_format((float) $dateRow['party_count'], 0) }} parties</p>
                            </div>
                            <p class="font-mono text-sm font-semibold text-indigo-700">{{ number_format((float) $dateRow['total_amount'], 2) }}</p>
                        </summary>

                        <div class="space-y-3 border-t border-gray-200 p-4">
                            @forelse ($dateRow['parties'] as $partyRow)
                                <details class="rounded-lg border border-gray-200 bg-gray-50">
                                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $partyRow['party_name'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $partyRow['party_phone'] ?: '-' }} • {{ number_format((float) $partyRow['bill_count'], 0) }} bills</p>
                                        </div>
                                        <p class="font-mono text-sm font-semibold text-indigo-700">{{ number_format((float) $partyRow['total_amount'], 2) }}</p>
                                    </summary>
                                    <div class="border-t border-gray-200 p-4">
                                        @include('reports.partials.line-breakdowns', [
                                            'generalized' => $partyRow['generalized'],
                                            'itemWise' => $partyRow['item_wise'],
                                            'includeExpense' => false,
                                        ])
                                    </div>
                                </details>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-4 text-center text-sm text-gray-500">No parties found for this date.</div>
                            @endforelse
                        </div>
                    </details>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">No date-party-wise rows found.</div>
                @endforelse
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            window.jQuery('#sales-report-party-filter').select2({
                width: '100%',
                placeholder: 'All parties',
                allowClear: true,
            });
        });
    </script>
@endpush
