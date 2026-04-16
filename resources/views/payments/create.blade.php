@extends('layouts.app')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-semibold text-gray-900">Create Payment</h1>
            <p class="mt-1 text-sm text-gray-500">Use Enter to move across fields. Linked bills still lock the payment direction automatically.</p>

            <form id="payment-create-form" action="{{ route('payments.store') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between">
                            <label for="payment-party-select" class="block text-sm font-medium text-gray-700">Party</label>
                            <button type="button" data-open-quick-party-entry data-party-select-id="payment-party-select" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Quick Add</button>
                        </div>
                        <select id="payment-party-select" name="party_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-enter-flow>
                            <option value="">Select a party</option>
                            @foreach ($parties as $party)
                                <option value="{{ $party->id }}" @selected($selectedPartyId === $party->id)>{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Payment Direction</label>
                        <select id="type" name="type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-enter-flow>
                            <option value="received" @selected($selectedType === 'received')>Received</option>
                            <option value="given" @selected($selectedType === 'given')>Given</option>
                        </select>
                    </div>
                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700">Account</label>
                        <select id="account_id" name="account_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-enter-flow>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected($selectedAccountId === $account->id)>{{ $account->name }} ({{ ucfirst($account->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                        <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" data-enter-flow>
                    </div>
                    <div>
                        <label for="cheque_number" class="block text-sm font-medium text-gray-700">Cheque Number</label>
                        <input id="cheque_number" name="cheque_number" type="text" maxlength="50" value="{{ old('cheque_number') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Optional" data-enter-flow>
                    </div>
                    <div>
                        <label for="sale_id" class="block text-sm font-medium text-gray-700">Linked Sale</label>
                        <select id="sale_id" name="sale_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-placeholder="Search linked sale" data-enter-flow>
                            <option value="">None</option>
                            @if ($selectedSaleOption)
                                <option value="{{ $selectedSaleOption['id'] }}" @selected((string) $selectedSaleId === (string) $selectedSaleOption['id'])>{{ $selectedSaleOption['text'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label for="purchase_id" class="block text-sm font-medium text-gray-700">Linked Purchase</label>
                        <select id="purchase_id" name="purchase_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" data-placeholder="Search linked purchase" data-enter-flow>
                            <option value="">None</option>
                            @if ($selectedPurchaseOption)
                                <option value="{{ $selectedPurchaseOption['id'] }}" @selected((string) $selectedPurchaseId === (string) $selectedPurchaseOption['id'])>{{ $selectedPurchaseOption['text'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <div id="payment-party-balance-card" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <p class="text-sm text-gray-500">Party Balance</p>
                            <p id="payment-party-balance-value" class="mt-1 font-mono text-xl font-semibold text-gray-700">0.00</p>
                            <p id="payment-party-balance-label" class="mt-1 text-sm text-gray-500">No party selected</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Standalone payments keep the direction you choose. Linked sale and purchase payments override it automatically.
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('payments.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700" data-enter-flow>Save Payment</button>
                </div>
            </form>
        </div>

        <aside class="hidden lg:block">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Recent Ledger</h2>
                        <p id="payment-sidebar-caption" class="mt-1 text-xs text-gray-500">Select a party to load recent ledger rows.</p>
                    </div>
                    <span id="payment-sidebar-limit-badge" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">10 rows</span>
                </div>

                <div class="mt-4 max-h-[200px] overflow-y-auto rounded-lg border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="sticky top-0 bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Date</th>
                                <th class="px-3 py-2 text-left font-semibold">Reference</th>
                                <th class="px-3 py-2 text-right font-semibold">Dr</th>
                                <th class="px-3 py-2 text-right font-semibold">Cr</th>
                            </tr>
                        </thead>
                        <tbody id="payment-sidebar-entries">
                            <tr id="payment-sidebar-empty">
                                <td colspan="4" class="px-3 py-6 text-center text-gray-500">No party selected.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            const $ = window.jQuery;
            const form = document.getElementById('payment-create-form');
            const $partySelect = $('#payment-party-select');
            const $typeSelect = $('#type');
            const $saleSelect = $('#sale_id');
            const $purchaseSelect = $('#purchase_id');
            const balanceValue = document.getElementById('payment-party-balance-value');
            const balanceLabel = document.getElementById('payment-party-balance-label');
            const balanceCard = document.getElementById('payment-party-balance-card');
            const sidebarEntries = document.getElementById('payment-sidebar-entries');
            const sidebarEmpty = document.getElementById('payment-sidebar-empty');
            const sidebarCaption = document.getElementById('payment-sidebar-caption');
            const sidebarLimitBadge = document.getElementById('payment-sidebar-limit-badge');
            const amountField = document.getElementById('amount');
            const balanceUrl = @json(route('payments.party-balance'));

            const initRemoteSelect = (selector, url) => {
                const $select = $(selector);

                if (! $select.length) {
                    return;
                }

                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }

                $select.select2({
                    width: '100%',
                    placeholder: $select.data('placeholder') || 'Search',
                    allowClear: true,
                    ajax: {
                        url,
                        dataType: 'json',
                        delay: 250,
                        data: params => ({
                            party_id: $partySelect.val() || null,
                            q: params.term || '',
                            page: params.page || 1,
                        }),
                        processResults: data => data,
                        cache: true,
                    },
                });
            };

            initRemoteSelect('#sale_id', @json(route('payments.search-sales')));
            initRemoteSelect('#purchase_id', @json(route('payments.search-purchases')));

            const renderBalance = data => {
                const label = data && data.label ? data.label : 'No party selected';
                const amount = data && data.formatted_amount ? data.formatted_amount : '0.00';
                const tone = data && data.tone ? data.tone : 'neutral';

                balanceValue.textContent = amount;
                balanceLabel.textContent = label;
                balanceValue.className = 'mt-1 font-mono text-xl font-semibold';
                balanceCard.className = 'rounded-lg border px-4 py-3';

                if (tone === 'positive') {
                    balanceCard.classList.add('border-green-200', 'bg-green-50');
                    balanceValue.classList.add('text-green-700');
                    balanceLabel.className = 'mt-1 text-sm text-green-700';
                    return;
                }

                if (tone === 'negative') {
                    balanceCard.classList.add('border-red-200', 'bg-red-50');
                    balanceValue.classList.add('text-red-600');
                    balanceLabel.className = 'mt-1 text-sm text-red-600';
                    return;
                }

                balanceCard.classList.add('border-gray-200', 'bg-gray-50');
                balanceValue.classList.add('text-gray-700');
                balanceLabel.className = 'mt-1 text-sm text-gray-500';
            };

            const renderSidebarEntries = (entries = [], limit = 10) => {
                sidebarEntries.querySelectorAll('[data-sidebar-entry]').forEach(row => row.remove());
                sidebarLimitBadge.textContent = `${limit} rows`;

                if (!entries.length) {
                    sidebarEmpty.classList.remove('hidden');
                    sidebarEmpty.querySelector('td').textContent = $partySelect.val()
                        ? 'No recent ledger rows for this party.'
                        : 'No party selected.';
                    sidebarCaption.textContent = $partySelect.val()
                        ? `Showing latest ${limit} ledger rows for the selected party.`
                        : 'Select a party to load recent ledger rows.';
                    return;
                }

                sidebarEmpty.classList.add('hidden');
                sidebarCaption.textContent = `Showing latest ${entries.length} of ${limit} ledger rows for the selected party.`;

                entries.forEach(entry => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-sidebar-entry', '1');
                    row.className = 'border-t border-gray-100 align-top';
                    row.innerHTML = `
                        <td class="px-3 py-2 text-gray-600">${entry.date || '-'}</td>
                        <td class="px-3 py-2 text-gray-700">
                            <div class="font-medium text-gray-800">${entry.reference || '-'}</div>
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">${entry.type || '-'}</div>
                        </td>
                        <td class="px-3 py-2 text-right font-mono text-green-700">${entry.receivable || '-'}</td>
                        <td class="px-3 py-2 text-right font-mono text-red-600">${entry.payable || '-'}</td>
                    `;
                    sidebarEntries.appendChild(row);
                });
            };

            const syncTypeLock = () => {
                if ($saleSelect.val()) {
                    $typeSelect.val('received');
                    $typeSelect.prop('disabled', true);
                    return;
                }

                if ($purchaseSelect.val()) {
                    $typeSelect.val('given');
                    $typeSelect.prop('disabled', true);
                    return;
                }

                $typeSelect.prop('disabled', false);
            };

            const updatePartyContext = () => {
                const partyId = $partySelect.val();

                if (!partyId) {
                    renderBalance(null);
                    renderSidebarEntries([], 10);
                    return;
                }

                const url = new URL(balanceUrl, window.location.origin);
                url.searchParams.set('party_id', partyId);

                window.fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(response => response.ok ? response.json() : null)
                    .then(data => {
                        renderBalance(data);
                        renderSidebarEntries(data?.recent_entries || [], data?.sidebar_limit || 10);

                        if (! $saleSelect.val() && ! $purchaseSelect.val() && data?.direction && ! $typeSelect.prop('disabled')) {
                            $typeSelect.val(data.direction);
                        }
                    })
                    .catch(() => {
                        renderBalance(null);
                        renderSidebarEntries([], 10);
                    });
            };

            const setupEnterFlow = () => {
                const fields = Array.from(form.querySelectorAll('[data-enter-flow]'))
                    .filter(field => !field.disabled && field.type !== 'hidden');

                fields.forEach((field, index) => {
                    field.addEventListener('keydown', event => {
                        if (event.key !== 'Enter' || event.shiftKey) {
                            return;
                        }

                        const isTextarea = field.tagName === 'TEXTAREA';
                        const isSubmit = field.tagName === 'BUTTON' && field.type === 'submit';

                        if (isTextarea || isSubmit) {
                            return;
                        }

                        event.preventDefault();

                        const nextField = fields[index + 1];
                        if (nextField) {
                            nextField.focus();
                        } else {
                            form.requestSubmit();
                        }
                    });
                });
            };

            $partySelect.on('change', () => {
                $saleSelect.val(null).trigger('change');
                $purchaseSelect.val(null).trigger('change');
                syncTypeLock();
                updatePartyContext();
            });

            $saleSelect.on('change', () => {
                if ($saleSelect.val()) {
                    $purchaseSelect.val(null).trigger('change.select2');
                }

                syncTypeLock();
            });

            $purchaseSelect.on('change', () => {
                if ($purchaseSelect.val()) {
                    $saleSelect.val(null).trigger('change.select2');
                }

                syncTypeLock();
            });

            syncTypeLock();
            setupEnterFlow();
            updatePartyContext();

            window.setTimeout(() => {
                if ($partySelect.val() && amountField) {
                    amountField.focus();
                    return;
                }

                const partyElement = $partySelect.get(0);
                if (partyElement) {
                    partyElement.focus();
                }
            }, 0);
        });
    </script>
@endpush
