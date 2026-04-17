@extends('layouts.app')

@section('content')
    @php
        $partyOptions = $parties->map(fn ($party) => [
            'id' => (string) $party->id,
            'label' => $party->name,
        ])->values()->all();

        $accountOptions = $accounts->map(fn ($account) => [
            'id' => (string) $account->id,
            'label' => sprintf('%s (%s)', $account->name, ucfirst($account->type)),
        ])->values()->all();
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Mass Payment</h1>
            <p class="text-sm text-gray-500">Select a date to load payments. Standalone rows can be added, updated, or deleted here; sale and purchase linked payments are shown read-only.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-[minmax(0,14rem)_auto_1fr] md:items-end">
                @include('partials.bs-date-selector', ['name' => 'date_bs', 'label' => 'Date', 'value' => $dateBs])
                <div class="flex gap-2">
                    <button type="button" id="mass-payment-load" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">Load Date</button>
                </div>
                <div id="mass-payment-feedback" class="hidden rounded-lg border px-3 py-2 text-sm"></div>
            </div>
        </div>

        @unless($hasAccounts)
            @include('partials.account-required-notice')
        @endunless

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Party</th>
                            <th class="px-4 py-3 text-left">Cash / Bank</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Notes</th>
                            <th class="px-4 py-3 text-left">Linked Bill</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="mass-payment-body">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading payments for the selected date.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 border-t border-gray-200 bg-gray-50 px-4 py-4 md:grid-cols-2">
                <div class="text-sm text-gray-500">Rows save immediately. Linked bill rows stay visible here but cannot be changed.</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Entries</p>
                        <div id="mass-payment-total-entries" class="mt-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-lg text-gray-800">0</div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Payment Amount</p>
                        <div id="mass-payment-total-amount" class="mt-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-lg text-gray-800">0.00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $ = window.jQuery;
            const csrfToken = @json(csrf_token());
            const initialDateBs = @json($dateBs);
            const hasAccounts = @json($hasAccounts);
            const partyOptions = @js($partyOptions);
            const accountOptions = @js($accountOptions);
            const loadUrl = @json(route('payments.mass.load'));
            const storeUrl = @json(route('payments.mass.rows.store'));
            const updateUrlTemplate = @json(route('payments.mass.rows.update', ['payment' => '__PAYMENT__']));
            const destroyUrlTemplate = @json(route('payments.mass.rows.destroy', ['payment' => '__PAYMENT__']));
            const body = document.getElementById('mass-payment-body');
            const dateField = document.querySelector('input[name="date_bs"]');
            const visibleDateField = document.querySelector('.bs-date-picker input[type="text"]');
            const loadButton = document.getElementById('mass-payment-load');
            const feedback = document.getElementById('mass-payment-feedback');
            const totalEntries = document.getElementById('mass-payment-total-entries');
            const totalAmount = document.getElementById('mass-payment-total-amount');

            if (dateField && !dateField.value) {
                dateField.value = initialDateBs;
            }

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const money = (value) => {
                const number = Number(value);
                return Number.isFinite(number) ? number.toFixed(2) : '0.00';
            };

            const optionMarkup = (options, selectedValue) => options.map((option) => `
                <option value="${escapeHtml(option.id)}" ${String(selectedValue) === String(option.id) ? 'selected' : ''}>
                    ${escapeHtml(option.label)}
                </option>
            `).join('');

            const typeOptions = (selectedValue) => `
                <option value="received" ${selectedValue === 'received' ? 'selected' : ''}>Received</option>
                <option value="given" ${selectedValue === 'given' ? 'selected' : ''}>Given</option>
            `;

            const renderEditableRow = (row) => `
                <tr class="border-t border-gray-100" data-loaded-row="1" data-row-id="${row.id}">
                    <td class="px-4 py-3">
                        <select class="mass-type w-full rounded border border-gray-300 px-2 py-1.5 text-sm" ${!row.can_edit ? 'disabled' : ''}>
                            ${typeOptions(row.type)}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <select class="mass-party-select mass-select2 w-full py-1.5 text-sm" data-placeholder="Select party" ${!row.can_edit ? 'disabled' : ''}>
                            <option value="">Select party</option>
                            ${optionMarkup(partyOptions, row.party_id)}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <select class="mass-account-select mass-select2 w-full py-1.5 text-sm" data-placeholder="Select account" ${!row.can_edit || !hasAccounts ? 'disabled' : ''}>
                            <option value="">Select account</option>
                            ${optionMarkup(accountOptions, row.account_id)}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" min="0.01" step="0.01" value="${escapeHtml(row.amount)}" class="mass-amount w-full rounded border border-gray-300 px-2 py-1.5 text-right text-sm" ${!row.can_edit ? 'disabled' : ''}>
                    </td>
                    <td class="px-4 py-3">
                        <input type="text" maxlength="255" value="${escapeHtml(row.notes || '')}" class="mass-notes w-full rounded border border-gray-300 px-2 py-1.5 text-sm" ${!row.can_edit ? 'disabled' : ''}>
                    </td>
                    <td class="px-4 py-3 text-gray-500">${escapeHtml(row.linked_label)}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-300" data-save-row ${!row.can_edit || !hasAccounts ? 'disabled' : ''}>Save</button>
                            ${row.can_delete
                                ? '<button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50" data-delete-row>Delete</button>'
                                : '<span class="text-xs text-gray-400">-</span>'}
                        </div>
                    </td>
                </tr>
            `;

            const renderLinkedRow = (row) => `
                <tr class="border-t border-gray-100 bg-gray-50/60" data-loaded-row="1" data-amount="${escapeHtml(row.amount)}">
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ${row.type === 'received' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${escapeHtml(row.type)}</span>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900">${escapeHtml(row.party_label)}</td>
                    <td class="px-4 py-3 text-gray-700">${escapeHtml(row.account_label)}</td>
                    <td class="px-4 py-3 text-right font-mono text-gray-800">${money(row.amount)}</td>
                    <td class="px-4 py-3 text-gray-600">${escapeHtml(row.notes || '-')}</td>
                    <td class="px-4 py-3 text-gray-600">${escapeHtml(row.linked_label)}</td>
                    <td class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Read only</td>
                </tr>
            `;

            const renderDraftRow = () => `
                <tr class="border-t border-indigo-100 bg-indigo-50/40" data-draft-row="1">
                    <td class="px-4 py-3">
                        <select class="draft-type w-full rounded border border-gray-300 px-2 py-1.5 text-sm" ${!hasAccounts ? 'disabled' : ''}>
                            ${typeOptions('received')}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <select class="draft-party mass-select2 w-full py-1.5 text-sm" data-placeholder="Select party" ${!hasAccounts ? 'disabled' : ''}>
                            <option value="">Select party</option>
                            ${optionMarkup(partyOptions, '')}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <select class="draft-account mass-select2 w-full py-1.5 text-sm" data-placeholder="Select account" ${!hasAccounts ? 'disabled' : ''}>
                            <option value="">Select account</option>
                            ${optionMarkup(accountOptions, '')}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" min="0.01" step="0.01" class="draft-amount w-full rounded border border-gray-300 px-2 py-1.5 text-right text-sm" placeholder="0.00" ${!hasAccounts ? 'disabled' : ''}>
                    </td>
                    <td class="px-4 py-3">
                        <input type="text" maxlength="255" class="draft-notes w-full rounded border border-gray-300 px-2 py-1.5 text-sm" placeholder="Notes" ${!hasAccounts ? 'disabled' : ''}>
                    </td>
                    <td class="px-4 py-3 text-gray-500">New</td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-300" data-add-row ${!hasAccounts ? 'disabled' : ''}>Add Row</button>
                    </td>
                </tr>
            `;

            const setFeedback = (message, tone = 'success') => {
                if (!message) {
                    feedback.classList.add('hidden');
                    feedback.textContent = '';
                    feedback.className = 'hidden rounded-lg border px-3 py-2 text-sm';
                    return;
                }

                feedback.textContent = message;
                feedback.className = 'rounded-lg border px-3 py-2 text-sm';
                feedback.classList.add(tone === 'error'
                    ? 'border-red-200'
                    : 'border-green-200');
                feedback.classList.add(tone === 'error'
                    ? 'bg-red-50'
                    : 'bg-green-50');
                feedback.classList.add(tone === 'error'
                    ? 'text-red-700'
                    : 'text-green-700');
            };

            const initSelect2 = () => {
                if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                    return;
                }

                $('.mass-select2').each(function () {
                    const $select = $(this);
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    $select.select2({
                        width: '100%',
                        placeholder: $select.data('placeholder') || 'Select',
                    });
                });
            };

            const updateTotals = () => {
                const loadedRows = Array.from(body.querySelectorAll('[data-loaded-row="1"]'));
                const total = loadedRows.reduce((sum, row) => {
                    const editableAmount = row.querySelector('.mass-amount');
                    if (editableAmount) {
                        return sum + (Number(editableAmount.value) || 0);
                    }

                    return sum + (Number(row.dataset.amount || 0) || 0);
                }, 0);

                totalEntries.textContent = String(loadedRows.length);
                totalAmount.textContent = money(total);
            };

            const renderRows = (rows) => {
                const existingMarkup = rows.length
                    ? rows.map((row) => row.is_linked ? renderLinkedRow(row) : renderEditableRow(row)).join('')
                    : '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No payments found for this date.</td></tr>';

                body.innerHTML = `${existingMarkup}${renderDraftRow()}`;
                initSelect2();
                updateTotals();
            };

            const getRowPayload = (rowElement, isDraft = false) => ({
                date_bs: dateField.value,
                type: rowElement.querySelector(isDraft ? '.draft-type' : '.mass-type')?.value || '',
                party_id: rowElement.querySelector(isDraft ? '.draft-party' : '.mass-party-select')?.value || '',
                account_id: rowElement.querySelector(isDraft ? '.draft-account' : '.mass-account-select')?.value || '',
                amount: rowElement.querySelector(isDraft ? '.draft-amount' : '.mass-amount')?.value || '',
                notes: rowElement.querySelector(isDraft ? '.draft-notes' : '.mass-notes')?.value || '',
            });

            const validatePayload = (payload) => {
                if (!payload.date_bs || !payload.type || !payload.party_id || !payload.account_id || !(Number(payload.amount) > 0)) {
                    setFeedback('Date, type, party, account, and amount are required.', 'error');
                    return false;
                }

                return true;
            };

            const requestJson = async (url, method = 'GET', payload = null) => {
                const options = {
                    method,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                };

                if (payload) {
                    options.headers['Content-Type'] = 'application/json';
                    options.headers['X-CSRF-TOKEN'] = csrfToken;
                    options.body = JSON.stringify(payload);
                } else if (method !== 'GET') {
                    options.headers['X-CSRF-TOKEN'] = csrfToken;
                }

                const response = await window.fetch(url, options);
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const errorMessage = data?.message
                        || Object.values(data?.errors || {}).flat()[0]
                        || 'Request failed.';
                    throw new Error(errorMessage);
                }

                return data;
            };

            const loadRows = async () => {
                setFeedback('');
                body.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading payments for the selected date.</td></tr>';

                try {
                    const url = new URL(loadUrl, window.location.origin);
                    url.searchParams.set('date_bs', dateField.value);
                    const data = await requestJson(url.toString());
                    renderRows(data.rows || []);
                } catch (error) {
                    body.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-600">Could not load payments for this date.</td></tr>';
                    updateTotals();
                    setFeedback(error.message, 'error');
                }
            };

            body.addEventListener('input', (event) => {
                if (event.target.matches('.mass-amount')) {
                    updateTotals();
                }
            });

            body.addEventListener('click', async (event) => {
                const addButton = event.target.closest('[data-add-row]');
                if (addButton) {
                    const row = body.querySelector('[data-draft-row="1"]');
                    const payload = getRowPayload(row, true);
                    if (!validatePayload(payload)) {
                        return;
                    }

                    addButton.disabled = true;

                    try {
                        await requestJson(storeUrl, 'POST', payload);
                        setFeedback('Mass payment row saved successfully.');
                        await loadRows();
                    } catch (error) {
                        setFeedback(error.message, 'error');
                    } finally {
                        addButton.disabled = false;
                    }

                    return;
                }

                const saveButton = event.target.closest('[data-save-row]');
                if (saveButton) {
                    const row = event.target.closest('[data-row-id]');
                    const payload = getRowPayload(row, false);
                    if (!validatePayload(payload)) {
                        return;
                    }

                    saveButton.disabled = true;

                    try {
                        await requestJson(updateUrlTemplate.replace('__PAYMENT__', row.dataset.rowId), 'PATCH', payload);
                        setFeedback('Mass payment row updated successfully.');
                        await loadRows();
                    } catch (error) {
                        setFeedback(error.message, 'error');
                    } finally {
                        saveButton.disabled = false;
                    }

                    return;
                }

                const deleteButton = event.target.closest('[data-delete-row]');
                if (deleteButton) {
                    const row = event.target.closest('[data-row-id]');
                    if (!row || !window.confirm('Delete this standalone payment row?')) {
                        return;
                    }

                    deleteButton.disabled = true;

                    try {
                        await requestJson(destroyUrlTemplate.replace('__PAYMENT__', row.dataset.rowId), 'DELETE');
                        setFeedback('Mass payment row deleted successfully.');
                        await loadRows();
                    } catch (error) {
                        setFeedback(error.message, 'error');
                    } finally {
                        deleteButton.disabled = false;
                    }
                }
            });

            loadButton.addEventListener('click', loadRows);
            dateField.addEventListener('change', loadRows);
            visibleDateField?.addEventListener('blur', () => window.setTimeout(loadRows, 150));
            visibleDateField?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    window.setTimeout(loadRows, 150);
                }
            });

            loadRows();
        });
    </script>
@endpush
