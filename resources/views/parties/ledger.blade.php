@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $party->name }} Ledger</h1>
                <p class="text-sm text-gray-500">Running balance derived entirely from ledger rows.</p>
            </div>
            <div class="flex items-center gap-3 print:hidden">
                <button type="button" onclick="printPartyLedgerTable()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Print Ledger</button>
                <a href="{{ route('parties.show', $party) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Back to party</a>
            </div>
        </div>

        <form method="GET" action="{{ route('parties.ledger', $party) }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm print:hidden">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
                </div>
                <div>
                    @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
                    <a href="{{ route('parties.ledger', $party) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </div>
        </form>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Opening Balance</p>
            <p class="mt-2 font-mono text-xl font-semibold {{ $openingBalance >= 0 ? 'text-green-600' : 'text-red-500' }}">
                {{ number_format(abs($openingBalance), 2) }} {{ $openingBalance >= 0 ? 'Receivable' : 'Payable' }}
            </p>
        </div>

        <div class="space-y-4" x-data="{ viewMode: 'table' }">
            <div class="md:hidden print:hidden">
                <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                    <button type="button" @click="viewMode = 'table'" class="px-4 py-2 text-sm font-medium" :class="viewMode === 'table' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Table</button>
                    <button type="button" @click="viewMode = 'card'" class="px-4 py-2 text-sm font-medium" :class="viewMode === 'card' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'">Card</button>
                </div>
            </div>

            <div id="party-ledger-print-table" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" x-show="viewMode === 'table'" x-cloak>
                <div class="overflow-x-auto">
                    <table class="min-w-[720px] w-full text-sm font-mono">
                        <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Reference</th>
                                <th class="px-4 py-3 text-right text-blue-600">Receivable</th>
                                <th class="px-4 py-3 text-right text-orange-500">Payable</th>
                                <th class="px-4 py-3 text-right">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $running = (float) $openingBalance; @endphp
                            @forelse ($ledgerRows as $row)
                                @php $running += ((float) $row->dr_amount - (float) $row->cr_amount); @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-3 text-gray-500">{{ $row->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 capitalize">{{ $row->type }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $row->reference_text ?? ($row->ref_table . ' / ' . $row->ref_id) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-600">{{ $row->dr_amount > 0 ? number_format($row->dr_amount, 2) : '—' }}</td>
                                    <td class="px-4 py-3 text-right text-orange-500">{{ $row->cr_amount > 0 ? number_format($row->cr_amount, 2) : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $running >= 0 ? 'text-green-600' : 'text-red-500' }}">{{ number_format(abs($running), 2) }} {{ $running >= 0 ? 'Receivable' : 'Payable' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center font-sans text-gray-500">No ledger rows found for this party.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-3 md:hidden print:hidden" x-show="viewMode === 'card'" x-cloak>
                @php $runningCard = (float) $openingBalance; @endphp
                @forelse ($ledgerRows as $row)
                    @php $runningCard += ((float) $row->dr_amount - (float) $row->cr_amount); @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 capitalize">{{ $row->type }}</p>
                                <p class="text-xs text-gray-500">{{ $row->created_at->format('d M Y') }}</p>
                            </div>
                            <p class="text-xs text-gray-500">{{ $row->reference_text ?? ($row->ref_table . ' / ' . $row->ref_id) }}</p>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs font-mono">
                            <div>
                                <p class="text-gray-500">Receivable</p>
                                <p class="font-semibold text-blue-600">{{ $row->dr_amount > 0 ? number_format($row->dr_amount, 2) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Payable</p>
                                <p class="font-semibold text-orange-500">{{ $row->cr_amount > 0 ? number_format($row->cr_amount, 2) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Balance</p>
                                <p class="font-semibold {{ $runningCard >= 0 ? 'text-green-600' : 'text-red-500' }}">{{ number_format(abs($runningCard), 2) }} {{ $runningCard >= 0 ? 'Receivable' : 'Payable' }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">No ledger rows found for this party.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function printPartyLedgerTable() {
            const tableContainer = document.getElementById('party-ledger-print-table');

            if (!tableContainer) {
                return;
            }

            const printWindow = window.open('', '_blank', 'width=1024,height=768');

            if (!printWindow) {
                return;
            }

            const partyName = @json($party->name . ' Ledger');
            const tableHtml = tableContainer.innerHTML;

            printWindow.document.open();
            printWindow.document.write(`
                <!doctype html>
                <html>
                    <head>
                        <meta charset="utf-8">
                        <title>${partyName}</title>
                        <style>
                            * { box-sizing: border-box; }
                            body { margin: 24px; font-family: Arial, sans-serif; color: #111827; }
                            h1 { margin: 0 0 16px; font-size: 20px; font-weight: 700; }
                            table { width: 100%; border-collapse: collapse; font-size: 12px; }
                            th, td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; text-align: left; }
                            th { background: #f3f4f6; text-transform: uppercase; font-size: 11px; letter-spacing: .03em; }
                            .text-right { text-align: right; }
                            .text-blue-600, .text-orange-500, .text-green-600, .text-red-500 { color: #111827 !important; }
                            .font-semibold { font-weight: 700; }
                            @page { size: A4 portrait; margin: 12mm; }
                        </style>
                    </head>
                    <body>
                        <h1>${partyName}</h1>
                        ${tableHtml}
                    </body>
                </html>
            `);
            printWindow.document.close();

            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            };
        }
    </script>
@endpush



