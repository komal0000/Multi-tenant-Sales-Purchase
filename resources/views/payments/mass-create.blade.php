@extends('layouts.app')

@section('content')
    <div class="space-y-6" x-data="massPaymentEntry({
        rows: @js($initialRows),
        paymentType: @js($paymentType),
    })">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ $pageTitle }}</h1>
            <p class="text-sm text-gray-500">Create multiple {{ $paymentType }} payments in one save using the selected batch date.</p>
        </div>

        <form action="{{ $submitRoute }}" method="POST" class="space-y-6" @submit="validateBeforeSubmit($event)">
            @csrf

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-[minmax(0,14rem)_minmax(0,16rem)_auto] md:items-end">
                    @include('partials.bs-date-selector', ['name' => 'date_bs', 'label' => 'Date', 'value' => $dateBs])
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Entry Type</label>
                        <div class="mt-1 rounded-xl border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-700">
                            {{ $pageTitle }}
                        </div>
                    </div>
                    <div class="md:pb-0.5">
                        <button type="button" @click="focus('draftParty')" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">Start Entry</button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Party</th>
                                <th class="px-4 py-3 text-left">Cash / Bank</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-left">Notes</th>
                                <th class="px-4 py-3 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="rows.length === 0">
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No mass-payment rows added yet.</td>
                                </tr>
                            </template>

                            <template x-for="(row, index) in rows" :key="`row-${index}`">
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-3">
                                        @foreach ($parties as $party)
                                            <span x-show="row.party_id === '{{ $party->id }}'" x-cloak>{{ $party->name }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3">
                                        @foreach ($accounts as $account)
                                            <span x-show="row.account_id === '{{ $account->id }}'" x-cloak>{{ $account->name }} ({{ ucfirst($account->type) }})</span>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono" x-text="money(row.amount)"></td>
                                    <td class="px-4 py-3 text-gray-600" x-text="row.notes || '-'"></td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" @click="removeRow(index)" class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Remove</button>
                                    </td>
                                </tr>
                            </template>

                            <tr class="border-t border-indigo-100 bg-indigo-50/40">
                                <td class="px-4 py-3">
                                    <select x-model="draftRow.party_id" x-ref="draftParty" @keydown.enter.prevent="focus('draftAccount')" class="w-full rounded border border-gray-300 px-2 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                                        <option value="">Select party</option>
                                        @foreach ($parties as $party)
                                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <select x-model="draftRow.account_id" x-ref="draftAccount" @keydown.enter.prevent="focus('draftAmount')" class="w-full rounded border border-gray-300 px-2 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                                        <option value="">Select account</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }} ({{ ucfirst($account->type) }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input x-model.number="draftRow.amount" x-ref="draftAmount" @keydown.enter.prevent="focus('draftNotes')" type="number" step="0.01" min="0.01" class="w-full rounded border border-gray-300 px-2 py-2 text-right text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="0.00">
                                </td>
                                <td class="px-4 py-3">
                                    <input x-model="draftRow.notes" x-ref="draftNotes" @keydown.enter.prevent="commitRow" type="text" maxlength="255" class="w-full rounded border border-gray-300 px-2 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Notes">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" @click="commitRow" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-lg font-semibold text-white hover:bg-indigo-700">+</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 border-t border-gray-200 bg-gray-50 px-4 py-4 md:grid-cols-2">
                    <div>
                        <p x-show="message" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700" x-text="message"></p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Entries</p>
                            <div class="mt-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-lg text-gray-800" x-text="rows.length"></div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Payment Amount</p>
                            <div class="mt-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-lg text-gray-800" x-text="money(totalAmount())"></div>
                        </div>
                    </div>
                </div>
            </div>

            <template x-for="(row, index) in rows" :key="`hidden-row-${index}`">
                <div>
                    <input type="hidden" :name="`rows[${index}][party_id]`" :value="row.party_id">
                    <input type="hidden" :name="`rows[${index}][account_id]`" :value="row.account_id">
                    <input type="hidden" :name="`rows[${index}][amount]`" :value="money(row.amount)">
                    <input type="hidden" :name="`rows[${index}][notes]`" :value="row.notes">
                </div>
            </template>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('payments.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save {{ $pageTitle }}</button>
            </div>
        </form>
    </div>

    <script>
        function massPaymentEntry(initialState) {
            return {
                rows: (initialState.rows || []).map((row) => ({
                    party_id: String(row.party_id || ''),
                    account_id: String(row.account_id || ''),
                    amount: Number(row.amount || 0),
                    notes: String(row.notes || ''),
                })),
                paymentType: String(initialState.paymentType || 'received'),
                message: '',
                draftRow: {
                    party_id: '',
                    account_id: '',
                    amount: '',
                    notes: '',
                },
                focus(refName) {
                    this.$nextTick(() => {
                        if (this.$refs[refName]) {
                            this.$refs[refName].focus();
                        }
                    });
                },
                money(value) {
                    const number = Number(value);

                    return Number.isFinite(number) ? number.toFixed(2) : '0.00';
                },
                totalAmount() {
                    return this.rows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                },
                resetDraft() {
                    this.draftRow = {
                        party_id: '',
                        account_id: '',
                        amount: '',
                        notes: '',
                    };
                },
                commitRow() {
                    this.message = '';

                    if (!this.draftRow.party_id || !this.draftRow.account_id || !(Number(this.draftRow.amount) > 0)) {
                        this.message = 'Select party, account, and amount before adding a row.';
                        this.focus(!this.draftRow.party_id ? 'draftParty' : (!this.draftRow.account_id ? 'draftAccount' : 'draftAmount'));

                        return;
                    }

                    this.rows.push({
                        party_id: String(this.draftRow.party_id),
                        account_id: String(this.draftRow.account_id),
                        amount: Number(this.draftRow.amount),
                        notes: String(this.draftRow.notes || '').trim(),
                    });

                    this.resetDraft();
                    this.focus('draftParty');
                },
                removeRow(index) {
                    this.rows.splice(index, 1);
                },
                validateBeforeSubmit(event) {
                    this.message = '';

                    if (this.rows.length === 0) {
                        event.preventDefault();
                        this.message = 'Add at least one payment row before saving.';
                        this.focus('draftParty');
                    }
                },
            };
        }
    </script>
@endsection
