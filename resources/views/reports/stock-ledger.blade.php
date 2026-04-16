@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Stock Ledger FIFO</h1>
            <p class="text-sm text-gray-500">Track stock movement, running quantity, FIFO issue valuation, and remaining layers by item.</p>
        </div>

        <form method="GET" action="{{ route('reports.stock-ledger') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="sales-purchase-report-filter-grid">
                <div>
                    <label for="stock-ledger-item-filter" class="block text-sm font-medium text-gray-700">Item</label>
                    <select id="stock-ledger-item-filter" name="item_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="">All items</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected((string) ($filters['item_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
                @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
                <div class="flex items-center gap-2 md:pb-0.5">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
                    <a href="{{ route('reports.stock-ledger') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </div>
        </form>

        @if (! $hasSearched)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                Use filters and click Apply to generate the FIFO stock ledger report.
            </div>
        @else
            <div class="space-y-4">
                @forelse ($reportRows as $itemRow)
                    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 px-4 py-4">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">{{ $itemRow['item_name'] }}</h2>
                                <p class="mt-1 text-xs text-gray-500">Opening {{ number_format((float) $itemRow['opening_qty'], 4) }} | Closing {{ number_format((float) $itemRow['closing_qty'], 4) }}</p>
                            </div>
                            <div class="grid gap-2 text-right sm:grid-cols-2">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Opening Value</p>
                                    <p class="font-mono text-sm font-semibold text-gray-800">{{ number_format((float) $itemRow['opening_value'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Closing Value</p>
                                    <p class="font-mono text-sm font-semibold text-indigo-700">{{ number_format((float) $itemRow['closing_value'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto px-4 py-4">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-left">Reference</th>
                                        <th class="px-3 py-2 text-center">Move</th>
                                        <th class="px-3 py-2 text-right">In Qty</th>
                                        <th class="px-3 py-2 text-right">Out Qty</th>
                                        <th class="px-3 py-2 text-right">Rate</th>
                                        <th class="px-3 py-2 text-right">FIFO Issue</th>
                                        <th class="px-3 py-2 text-right">Running Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($itemRow['rows'] as $row)
                                        <tr class="border-t border-gray-100">
                                            <td class="px-3 py-2 text-gray-600">
                                                <div>{{ $row['date_bs'] }}</div>
                                                <div class="text-xs text-gray-400">{{ $row['date_ad'] }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-gray-800">{{ $row['reference'] }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $row['movement'] === 'In' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                    {{ $row['movement'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono">{{ $row['in_qty'] !== null ? number_format((float) $row['in_qty'], 4) : '-' }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ $row['out_qty'] !== null ? number_format((float) $row['out_qty'], 4) : '-' }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $row['rate'], 4) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ $row['issue_value'] !== null ? number_format((float) $row['issue_value'], 2) : '-' }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $row['running_qty'], 4) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-3 py-6 text-center text-gray-500">No stock movement in this period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-gray-200 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Closing FIFO Layers</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($itemRow['closing_layers'] as $layer)
                                    <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-mono text-gray-700">
                                        {{ number_format((float) $layer['qty'], 4) }} @ {{ number_format((float) $layer['rate'], 4) }} = {{ number_format((float) $layer['value'], 2) }}
                                    </span>
                                @empty
                                    <span class="text-sm text-gray-500">No remaining layers.</span>
                                @endforelse
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                        No stock-ledger rows matched the selected filters.
                    </div>
                @endforelse
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            window.jQuery('#stock-ledger-item-filter').select2({
                width: '100%',
                placeholder: 'All items',
                allowClear: true,
            });
        });
    </script>
@endpush
