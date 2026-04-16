@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $party->name }} Ledger</h1>
                <p class="text-sm text-gray-500">Running balance derived entirely from ledger rows.</p>
            </div>
            <div class="flex items-center gap-3 print:hidden">
                <button type="button" data-open-quick-payment-modal class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Quick Payment</button>
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
            <p class="text-sm text-gray-500">Balance Before Range</p>
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
                                <th class="px-4 py-3 text-right text-blue-600">Dr</th>
                                <th class="px-4 py-3 text-right text-orange-500">Cr</th>
                                <th class="px-4 py-3 text-right">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $running = (float) $openingBalance; @endphp
                            @forelse ($ledgerRows as $row)
                                @php $running += ((float) $row->dr_amount - (float) $row->cr_amount); @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-3 text-gray-500">{{ $row->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $row->type) }}</td>
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
                                <p class="text-sm font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $row->type) }}</p>
                                <p class="text-xs text-gray-500">{{ $row->created_at->format('d M Y') }}</p>
                            </div>
                            <p class="text-xs text-gray-500">{{ $row->reference_text ?? ($row->ref_table . ' / ' . $row->ref_id) }}</p>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs font-mono">
                            <div>
                                <p class="text-gray-500">Dr</p>
                                <p class="font-semibold text-blue-600">{{ $row->dr_amount > 0 ? number_format($row->dr_amount, 2) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Cr</p>
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

        <div id="quick-payment-modal" class="fixed inset-0 z-[120] hidden" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900/50" data-quick-payment-backdrop></div>

            <div class="relative mx-auto mt-10 w-[95%] max-w-xl rounded-xl border border-gray-200 bg-white shadow-xl sm:mt-16">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Payment</h2>
                    <button type="button" data-quick-payment-close class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                </div>

                <form id="quick-payment-form" action="{{ route('payments.store') }}" method="POST" class="space-y-4 p-5">
                    @csrf
                    <input type="hidden" name="party_id" value="{{ $party->id }}">

                    <div id="quick-payment-errors" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

                    @unless($hasAccounts)
                        @include('partials.account-required-notice', ['accountsCreateUrl' => route('accounts.create')])
                    @endunless

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="quick_payment_type" class="block text-sm font-medium text-gray-700">Direction</label>
                            <select id="quick_payment_type" name="type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" @disabled(! $hasAccounts)>
                                <option value="received" @selected($currentBalance >= 0)>Received</option>
                                <option value="given" @selected($currentBalance < 0)>Given</option>
                            </select>
                        </div>
                        <div>
                            <label for="quick_payment_account" class="block text-sm font-medium text-gray-700">Account</label>
                            <select id="quick_payment_account" name="account_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" @disabled(! $hasAccounts)>
                                @unless($hasAccounts)
                                    <option value="">No account available</option>
                                @endunless
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected($defaultCashAccountId === $account->id)>{{ $account->name }} ({{ ucfirst($account->type) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="quick_payment_amount" class="block text-sm font-medium text-gray-700">Amount</label>
                            <input id="quick_payment_amount" name="amount" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" @disabled(! $hasAccounts)>
                        </div>
                        <div>
                            <label for="quick_payment_cheque" class="block text-sm font-medium text-gray-700">Cheque Number</label>
                            <input id="quick_payment_cheque" name="cheque_number" type="text" maxlength="50" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Optional" @disabled(! $hasAccounts)>
                        </div>
                    </div>

                    <div>
                        <label for="quick_payment_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <input id="quick_payment_notes" name="notes" type="text" maxlength="255" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Optional" @disabled(! $hasAccounts)>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" data-quick-payment-close class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" id="quick-payment-submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-green-300" @disabled(! $hasAccounts)>Save Payment</button>
                    </div>
                </form>
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

        (function () {
            const modal = document.getElementById('quick-payment-modal');
            const form = document.getElementById('quick-payment-form');
            const errorsBox = document.getElementById('quick-payment-errors');
            const submitButton = document.getElementById('quick-payment-submit');
            const amountInput = document.getElementById('quick_payment_amount');

            if (!modal || !form || !errorsBox || !submitButton) {
                return;
            }

            const setOpen = (isOpen) => {
                if (isOpen) {
                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                    amountInput?.focus();
                    return;
                }

                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                form.reset();
                errorsBox.classList.add('hidden');
                errorsBox.innerHTML = '';
            };

            const renderErrors = (payload) => {
                const messages = Object.values(payload || {}).flat().filter(Boolean);
                const list = document.createElement('ul');
                list.className = 'list-disc space-y-1 pl-5';

                (messages.length ? messages : ['Could not save payment.']).forEach((message) => {
                    const item = document.createElement('li');
                    item.textContent = String(message);
                    list.appendChild(item);
                });

                errorsBox.replaceChildren(list);
                errorsBox.classList.remove('hidden');
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-open-quick-payment-modal]')) {
                    event.preventDefault();
                    setOpen(true);
                    return;
                }

                if (event.target.closest('[data-quick-payment-close]') || event.target.closest('[data-quick-payment-backdrop]')) {
                    event.preventDefault();
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    setOpen(false);
                }
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                submitButton.disabled = true;
                errorsBox.classList.add('hidden');
                errorsBox.innerHTML = '';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        renderErrors(payload.errors || { request: [payload.message || 'Could not save payment.'] });
                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    renderErrors({ request: ['Could not save payment. Please try again.'] });
                } finally {
                    submitButton.disabled = false;
                }
            });
        })();
    </script>
@endpush
