@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Parties</h1>
                <p class="text-sm text-gray-500">Track receivable and payable balances from the ledger.</p>
            </div>
            <button
                type="button"
                data-open-quick-party-entry
                data-party-post-save="reload"
                class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 md:hidden"
            >
                + Quick Add
            </button>
        </div>

        <form method="GET" action="{{ route('parties.index') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Search Parties</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Name, phone or address" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div class="flex items-center gap-3 md:pb-0.5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('parties.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-4 text-left">Name</th>
                            <th class="px-5 py-4 text-left">Phone</th>
                            <th class="px-5 py-4 text-left">Address</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="party-table-body">
                        <tr id="party-inline-entry-row" class="border-t border-indigo-100 bg-indigo-50/50">
                            <td class="px-5 py-3">
                                <input
                                    id="party-inline-name"
                                    type="text"
                                    placeholder="Party name"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                    autocomplete="off"
                                >
                            </td>
                            <td class="px-5 py-3">
                                <input
                                    id="party-inline-phone"
                                    type="tel"
                                    placeholder="Phone"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                    autocomplete="off"
                                >
                            </td>
                            <td class="px-5 py-3">
                                <input
                                    id="party-inline-address"
                                    type="text"
                                    placeholder="Address"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                    autocomplete="off"
                                >
                            </td>
                            <td class="px-5 py-3">
                                <div class="grid gap-2">
                                    <input
                                        id="party-inline-opening-balance"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="0"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                        placeholder="Opening balance"
                                    >
                                    <select
                                        id="party-inline-opening-balance-side"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                    >
                                        <option value="dr" selected>Receivable</option>
                                        <option value="cr">Payable</option>
                                    </select>
                                    <input
                                        id="party-inline-opening-balance-date-bs"
                                        type="text"
                                        value="{{ \App\Helpers\DateHelper::getCurrentBS() }}"
                                        placeholder="YYYY-MM-DD"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                        autocomplete="off"
                                    >
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end">
                                    <button
                                        id="party-inline-save"
                                        type="button"
                                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                                    >
                                        Save
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="party-inline-error-row" class="hidden border-t border-red-100 bg-red-50">
                            <td colspan="5" class="px-5 py-3 text-sm text-red-700">
                                <ul id="party-inline-errors" class="list-disc space-y-1 pl-5"></ul>
                            </td>
                        </tr>
                        @forelse ($parties as $party)
                            <tr data-party-row class="border-t border-gray-100 hover:bg-gray-50/80">
                                <td class="px-5 py-4 font-medium text-gray-900">
                                    <a href="{{ route('parties.show', $party) }}" class="hover:text-indigo-600">{{ $party->name }}</a>
                                </td>
                                <td class="px-5 py-4 text-gray-500">{{ $party->phone ?: '-' }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ $party->address ?: '-' }}</td>
                                <td class="px-5 py-4 text-right font-mono font-semibold {{ $party->balance > 0 ? 'text-green-600' : ($party->balance < 0 ? 'text-red-500' : 'text-gray-500') }}">
                                    {{ number_format(abs($party->balance), 2) }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="{{ route('parties.ledger', $party) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Ledger</a>
                                        <form action="{{ route('parties.destroy', $party) }}" method="POST" onsubmit="return confirm('Delete this party? Existing related records may prevent deletion.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty-row>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-500">No parties created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3 md:hidden">
            @forelse ($parties as $party)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('parties.show', $party) }}" class="font-semibold text-gray-900">{{ $party->name }}</a>
                            <p class="mt-1 text-sm text-gray-500">{{ $party->phone ?: 'No phone number' }}</p>
                            <p class="text-xs text-gray-500">{{ $party->address ?: 'No address' }}</p>
                        </div>
                        <span class="font-mono text-sm font-semibold {{ $party->balance > 0 ? 'text-green-600' : ($party->balance < 0 ? 'text-red-500' : 'text-gray-500') }}">
                            {{ number_format(abs($party->balance), 2) }}
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
                        <a href="{{ route('parties.ledger', $party) }}" class="text-sm font-medium text-indigo-600">View Ledger</a>
                        <form action="{{ route('parties.destroy', $party) }}" method="POST" onsubmit="return confirm('Delete this party? Existing related records may prevent deletion.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-red-500">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                    No parties created yet.
                </div>
            @endforelse
        </div>

        {{ $parties->links() }}
    </div>

    <script>
        (function () {
            const tableBody = document.getElementById('party-table-body');
            const nameInput = document.getElementById('party-inline-name');
            const phoneInput = document.getElementById('party-inline-phone');
            const addressInput = document.getElementById('party-inline-address');
            const openingBalanceInput = document.getElementById('party-inline-opening-balance');
            const openingBalanceSideSelect = document.getElementById('party-inline-opening-balance-side');
            const openingBalanceDateInput = document.getElementById('party-inline-opening-balance-date-bs');
            const saveButton = document.getElementById('party-inline-save');
            const errorRow = document.getElementById('party-inline-error-row');
            const errorList = document.getElementById('party-inline-errors');

            if (!tableBody || !nameInput || !phoneInput || !addressInput || !openingBalanceInput || !openingBalanceSideSelect || !saveButton || !errorRow || !errorList) {
                return;
            }

            const storeUrl = @json(route('parties.store'));
            const showUrlTemplate = @json(route('parties.show', ['party' => '__PARTY_ID__']));
            const ledgerUrlTemplate = @json(route('parties.ledger', ['party' => '__PARTY_ID__']));
            const destroyUrlTemplate = @json(route('parties.destroy', ['party' => '__PARTY_ID__']));
            const csrfToken = @json(csrf_token());
            const pageLimit = 20;
            const numberFormatter = new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            let isSaving = false;
            let shouldFocusNameAfterSave = false;

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (char) => {
                    const entities = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    };

                    return entities[char] ?? char;
                });
            }

            function clearErrors() {
                errorList.replaceChildren();
                errorRow.classList.add('hidden');
            }

            function renderErrors(errorPayload) {
                const messages = [];

                Object.values(errorPayload || {}).forEach((value) => {
                    if (Array.isArray(value)) {
                        value.forEach((message) => messages.push(String(message)));
                        return;
                    }

                    if (value) {
                        messages.push(String(value));
                    }
                });

                if (!messages.length) {
                    messages.push('Could not save party. Please try again.');
                }

                errorList.replaceChildren();

                messages.forEach((message) => {
                    const item = document.createElement('li');
                    item.textContent = message;
                    errorList.appendChild(item);
                });

                errorRow.classList.remove('hidden');
            }

            function setSavingState(saving) {
                isSaving = saving;
                saveButton.disabled = saving;
                nameInput.disabled = saving;
                phoneInput.disabled = saving;
                addressInput.disabled = saving;
                openingBalanceInput.disabled = saving;
                openingBalanceSideSelect.disabled = saving;
                openingBalanceDateInput.disabled = saving;
                saveButton.textContent = saving ? 'Saving...' : 'Save';
            }

            function resetInputs() {
                nameInput.value = '';
                phoneInput.value = '';
                addressInput.value = '';
                openingBalanceInput.value = '0';
                openingBalanceSideSelect.value = 'dr';
                openingBalanceDateInput.value = @json(\App\Helpers\DateHelper::getCurrentBS());
            }

            function buildPartyRow(party) {
                const openingBalance = Number(party.opening_balance ?? 0);
                const balanceSide = String(party.opening_balance_side ?? 'dr');
                const signedBalance = balanceSide === 'cr' ? -openingBalance : openingBalance;
                const balanceClass = signedBalance > 0 ? 'text-green-600' : (signedBalance < 0 ? 'text-red-500' : 'text-gray-500');

                const partyId = encodeURIComponent(String(party.id));
                const showUrl = showUrlTemplate.replace('__PARTY_ID__', partyId);
                const ledgerUrl = ledgerUrlTemplate.replace('__PARTY_ID__', partyId);
                const destroyUrl = destroyUrlTemplate.replace('__PARTY_ID__', partyId);

                const row = document.createElement('tr');
                row.setAttribute('data-party-row', '');
                row.className = 'border-t border-gray-100 hover:bg-gray-50/80';
                row.innerHTML = `
                    <td class="px-5 py-4 font-medium text-gray-900">
                        <a href="${showUrl}" class="hover:text-indigo-600">${escapeHtml(party.name)}</a>
                    </td>
                    <td class="px-5 py-4 text-gray-500">${escapeHtml(party.phone || '-')}</td>
                    <td class="px-5 py-4 text-gray-500">${escapeHtml(party.address || '-')}</td>
                    <td class="px-5 py-4 text-right font-mono font-semibold ${balanceClass}">
                        ${escapeHtml(numberFormatter.format(Math.abs(signedBalance)))}
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-4">
                            <a href="${ledgerUrl}" class="text-sm text-indigo-600 hover:text-indigo-700">Ledger</a>
                            <form action="${destroyUrl}" method="POST" onsubmit="return confirm('Delete this party? Existing related records may prevent deletion.')">
                                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                            </form>
                        </div>
                    </td>
                `;

                return row;
            }

            function prependPartyRow(party) {
                const emptyRow = tableBody.querySelector('[data-empty-row]');
                if (emptyRow) {
                    emptyRow.remove();
                }

                const newRow = buildPartyRow(party);
                const firstDataRow = tableBody.querySelector('[data-party-row]');

                if (firstDataRow) {
                    tableBody.insertBefore(newRow, firstDataRow);
                } else {
                    tableBody.appendChild(newRow);
                }

                const dataRows = tableBody.querySelectorAll('[data-party-row]');
                if (dataRows.length > pageLimit) {
                    dataRows[dataRows.length - 1].remove();
                }
            }

            async function saveInlineParty() {
                if (isSaving) {
                    return;
                }

                clearErrors();

                const name = nameInput.value.trim();
                const phone = phoneInput.value.trim();
                const address = addressInput.value.trim();
                const openingBalance = Number(openingBalanceInput.value || 0);
                const openingBalanceSide = openingBalanceSideSelect.value === 'cr' ? 'cr' : 'dr';
                const openingBalanceDateBs = openingBalanceDateInput.value.trim();

                if (!name) {
                    renderErrors({ name: ['Name is required.'] });
                    nameInput.focus();
                    return;
                }

                if (Number.isNaN(openingBalance) || openingBalance < 0) {
                    renderErrors({ opening_balance: ['Opening balance must be zero or positive.'] });
                    openingBalanceInput.focus();
                    return;
                }

                const formData = new FormData();
                formData.append('_token', csrfToken);
                formData.append('name', name);

                if (phone) {
                    formData.append('phone', phone);
                }

                if (address) {
                    formData.append('address', address);
                }

                formData.append('opening_balance', String(openingBalance));
                formData.append('opening_balance_side', openingBalanceSide);
                formData.append('opening_balance_date_bs', openingBalanceDateBs);

                setSavingState(true);
                shouldFocusNameAfterSave = false;

                try {
                    const response = await fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        if (response.status === 422 && payload.errors) {
                            renderErrors(payload.errors);
                        } else {
                            renderErrors({ request: [payload.message || 'Could not save party. Please try again.'] });
                        }

                        return;
                    }

                    if (payload.party) {
                        prependPartyRow(payload.party);
                    }

                    resetInputs();
                    shouldFocusNameAfterSave = true;
                } catch (error) {
                    renderErrors({ request: ['Could not save party. Please check your connection and try again.'] });
                } finally {
                    setSavingState(false);

                    if (shouldFocusNameAfterSave) {
                        nameInput.focus();
                    }
                }
            }

            saveButton.addEventListener('click', (event) => {
                event.preventDefault();
                saveInlineParty();
            });

            const inputFlow = [nameInput, phoneInput, addressInput];

            inputFlow.forEach((input, index) => {
                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    event.preventDefault();

                    const nextInput = inputFlow[index + 1];
                    if (nextInput) {
                        nextInput.focus();
                        return;
                    }

                    saveInlineParty();
                });
            });
        })();
    </script>
@endsection
