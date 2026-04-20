@extends('layouts.app')

@section('content')
    @php
        $itemCatalog = $itemsCatalog
            ->map(fn ($item) => [
                'id' => (string) $item->id,
                'name' => (string) $item->name,
                'qty' => (float) $item->qty,
                'rate' => (float) $item->rate,
                'cost_price' => (float) $item->cost_price,
            ])
            ->values()
            ->all();

        $expenseCategoryCatalog = $expenseCategories
            ->map(fn ($category) => [
                'id' => (string) $category->id,
                'name' => (string) $category->name,
                'parent_id' => filled($category->parent_id) ? (string) $category->parent_id : null,
            ])
            ->values()
            ->all();

        $initialItems = collect(old('items', []))
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                $lineType = $item['line_type']
                    ?? (filled($item['expense_category_id'] ?? null)
                        ? 'expense'
                        : (filled($item['item_id'] ?? null) ? 'item' : 'general'));

                if (! in_array($lineType, ['item', 'general', 'expense'], true)) {
                    $lineType = 'general';
                }

                return [
                    'line_type' => $lineType,
                    'item_id' => filled($item['item_id'] ?? null) ? (string) $item['item_id'] : '',
                    'description' => filled($item['description'] ?? null)
                        ? trim((string) $item['description'])
                        : trim((string) ($item['particular'] ?? '')),
                    'expense_category_id' => filled($item['expense_category_id'] ?? null) ? (string) $item['expense_category_id'] : '',
                    'qty' => (float) ($item['qty'] ?? 1),
                    'rate' => (float) ($item['rate'] ?? $item['price'] ?? 0),
                    'total' => (float) ($item['total'] ?? (($item['qty'] ?? 1) * ($item['rate'] ?? $item['price'] ?? 0))),
                ];
            })
            ->filter(fn (array $item) => $item['item_id'] !== '' || $item['description'] !== '' || $item['expense_category_id'] !== '' || $item['qty'] > 0 || $item['rate'] > 0)
            ->values()
            ->all();

        $initialPayments = collect(old('payments', []))
            ->map(fn ($payment) => [
                'account_id' => (string) ($payment['account_id'] ?? ''),
                'amount' => (float) ($payment['amount'] ?? 0),
                'cheque_number' => (string) ($payment['cheque_number'] ?? ''),
            ])
            ->filter(fn (array $payment) => $payment['account_id'] !== '' || $payment['amount'] > 0 || $payment['cheque_number'] !== '')
            ->values()
            ->all();
    @endphp

    <div class="space-y-5" x-data="transactionEntryForm({
        partyId: @js(old('party_id', '')),
        items: @js($initialItems),
        payments: @js($initialPayments),
        itemsCatalog: @js($itemCatalog),
        expenseCategories: @js($expenseCategoryCatalog),
        defaultCashAccountId: @js($defaultCashAccountId),
        lineTypeStorageKey: 'purchase-entry-line-type',
    })">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Purchase Entry</h1>
            <p class="text-sm text-gray-500">Use item, general, or expense lines. Only item lines affect stock quantity.</p>
        </div>

        <form action="{{ route('purchases.store') }}" method="POST" class="space-y-5" @submit="validateBeforeSubmit($event)">
            @csrf

            @unless($hasAccounts)
                @include('partials.account-required-notice')
            @endunless

            <div class="rounded-xl border border-gray-300 bg-white p-3 shadow-sm">
                <div class="grid gap-3 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-3">
                        @include('partials.bs-date-selector', ['name' => 'date_bs', 'label' => 'BS Date', 'value' => old('date_bs', $currentBsDate)])
                    </div>
                    <div class="md:col-span-8">
                        <div class="flex items-center justify-between gap-3">
                            <label for="purchase-party-select" class="text-sm font-semibold text-gray-700">Party</label>
                            <button type="button" data-open-quick-party-entry data-party-select-id="purchase-party-select" data-quick-party-hide-opening="true" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Quick Add</button>
                        </div>
                        <select id="purchase-party-select" name="party_id" x-model="partyId" x-ref="party" @keydown.enter.prevent="focus('itemType')" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" required>
                            <option value="">Select party</option>
                            @foreach ($parties as $party)
                                <option value="{{ $party->id }}">{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Bill Total</p>
                        <p class="mt-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-right font-mono text-base font-semibold text-indigo-700" x-text="money(grandTotal())"></p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-300 bg-white shadow-sm">
                <div class="border-b border-gray-300 px-3 py-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-gray-900">Line Items</h2>
                        <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                            <button type="button" data-open-quick-item-entry class="text-indigo-600 hover:text-indigo-700">Quick Add Item</button>
                            <button type="button" x-bind:data-expense-categories="expenseCategoryCatalogJson()" data-open-quick-expense-category-entry class="text-indigo-600 hover:text-indigo-700">Quick Add Category</button>
                            <a href="{{ route('items.index') }}" class="text-indigo-600 hover:text-indigo-700">Manage Items</a>
                            <a href="{{ route('expense-categories.index') }}" class="text-indigo-600 hover:text-indigo-700">Manage Expense Categories</a>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-2 border-b border-gray-300 bg-gray-50 px-3 py-2.5">
                    <div class="col-span-6 md:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Line Type</label>
                        <select x-model="draftItem.line_type" x-ref="itemType" @change="onDraftLineTypeChanged()" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                            <option value="item">Item</option>
                            <option value="general">General</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-3" x-show="draftItem.line_type === 'item'" x-cloak>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Item</label>
                        <select id="purchase-item-select" x-ref="itemSelect" class="select2 mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                            <option value="">Select item</option>
                            @foreach ($itemCatalog as $catalogItem)
                                <option value="{{ $catalogItem['id'] }}">{{ $catalogItem['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-3" x-show="draftItem.line_type === 'expense'" x-cloak>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Expense Category</label>
                        <select id="purchase-expense-category-select" x-model="draftItem.expense_category_id" x-ref="expenseCategory" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                            <option value="">Select expense category</option>
                            @foreach ($expenseCategoryCatalog as $category)
                                <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-3" x-show="draftItem.line_type === 'general'" x-cloak>
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Description</label>
                        <input x-model="draftItem.description" x-ref="itemDescription" @keydown.enter.prevent="focusAndSelect('itemQty')" enterkeyhint="next" type="text" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Custom particular">
                    </div>
                    <div class="col-span-4 md:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Qty</label>
                        <input x-model.number="draftItem.qty" x-ref="itemQty" @focus="selectInputValue($event)" @wheel.prevent @input="onDraftQtyChanged" @keydown.enter.prevent="focusAndSelect('itemRate')" enterkeyhint="next" type="number" step="0.0001" min="0.0001" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-right text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div class="col-span-4 md:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Rate</label>
                        <input x-model.number="draftItem.rate" x-ref="itemRate" @focus="selectInputValue($event)" @wheel.prevent @input="updateDraftItemFromRate" @keydown.enter.prevent="focusAndSelect('itemTotal')" enterkeyhint="next" type="number" step="0.0001" min="0" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-right text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div class="col-span-4 md:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Total</label>
                        <input x-model.number="draftItem.total" x-ref="itemTotal" @focus="selectInputValue($event)" @wheel.prevent @input="updateDraftItemFromTotal" @keydown.enter.prevent="commitItemFromTotal()" enterkeyhint="done" type="number" step="0.01" min="0" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-right text-sm font-mono text-gray-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div class="col-span-12 md:col-span-1">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-transparent select-none">Action</label>
                        <button type="button" @click="commitItem" class="mt-1 inline-flex h-[38px] w-full min-w-[3.25rem] items-center justify-center rounded bg-indigo-600 px-2 text-xl font-bold leading-none text-white hover:bg-indigo-700" aria-label="Add line">+</button>
                    </div>
                </div>

                <div class="overflow-x-auto px-3 py-2.5">
                    <table class="min-w-full border border-gray-300 text-sm">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border border-gray-300 px-2 py-1 text-left">Type</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Particular</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Qty</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Rate</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Total</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="6" class="border border-gray-300 px-2 py-3 text-center text-gray-500">No line items added yet.</td>
                                </tr>
                            </template>
                            <template x-for="(item, index) in items" :key="`item-${index}`">
                                <tr>
                                    <td class="border border-gray-300 px-2 py-1" x-text="lineTypeLabel(item.line_type)"></td>
                                    <td class="border border-gray-300 px-2 py-1" x-text="lineLabel(item)"></td>
                                    <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="qty(item.qty)"></td>
                                    <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="rate(item.rate)"></td>
                                    <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="money(item.total)"></td>
                                    <td class="border border-gray-300 px-2 py-1 text-center">
                                        <button type="button" @click="removeItem(index)" class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-300 bg-white shadow-sm">
                <div class="border-b border-gray-300 px-3 py-2.5">
                    <h2 class="font-semibold text-gray-900">Payment</h2>
                </div>

                <div class="grid grid-cols-12 gap-2 border-b border-gray-300 bg-gray-50 px-3 py-2.5">
                    <div class="col-span-6 md:col-span-4">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Payment Via</label>
                        <select x-model="draftPayment.account_id" x-ref="paymentAccount" @keydown.enter.prevent="focusAndSelect('paymentAmount')" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" @disabled(! $hasAccounts)>
                            @if (! $defaultCashAccountId)
                                <option value="">Select account</option>
                            @endif
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ ucfirst($account->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Payment Amount</label>
                        <input x-model.number="draftPayment.amount" x-ref="paymentAmount" @focus="selectInputValue($event)" @wheel.prevent @keydown.enter.prevent="focus('paymentCheque')" enterkeyhint="next" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-right text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="0.00">
                    </div>
                    <div class="col-span-8 md:col-span-3">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">Cheque Number</label>
                        <input x-model="draftPayment.cheque_number" x-ref="paymentCheque" @keydown.enter.prevent="commitPayment" enterkeyhint="done" type="text" maxlength="50" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Optional">
                    </div>
                    <div class="col-span-4 md:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-transparent">Action</label>
                        <button type="button" @click="commitPayment" class="mt-1 w-full rounded bg-indigo-600 px-2 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-300" @disabled(! $hasAccounts)>ADD</button>
                    </div>
                </div>

                <div class="overflow-x-auto px-3 py-2.5">
                    <table class="min-w-full border border-gray-300 text-sm">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border border-gray-300 px-2 py-1 text-left">Account</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Amount</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Cheque Number</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="payments.length === 0">
                                <tr>
                                    <td colspan="4" class="border border-gray-300 px-2 py-3 text-center text-gray-500">No payments added.</td>
                                </tr>
                            </template>
                            <template x-for="(payment, index) in payments" :key="`payment-${index}`">
                                <tr>
                                    <td class="border border-gray-300 px-2 py-1" x-text="accountName(payment.account_id)"></td>
                                    <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="money(payment.amount)"></td>
                                    <td class="border border-gray-300 px-2 py-1" x-text="payment.cheque_number || '-'"></td>
                                    <td class="border border-gray-300 px-2 py-1 text-center">
                                        <button type="button" @click="removePayment(index)" class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-gray-800">
                            <tr>
                                <td class="border border-gray-300 px-2 py-1 text-right">Paid</td>
                                <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="money(paymentTotal())"></td>
                                <td class="border border-gray-300 px-2 py-1"></td>
                                <td class="border border-gray-300 px-2 py-1"></td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1 text-right">Due</td>
                                <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="money(dueTotal())"></td>
                                <td class="border border-gray-300 px-2 py-1"></td>
                                <td class="border border-gray-300 px-2 py-1"></td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1 text-right">Advance Given</td>
                                <td class="border border-gray-300 px-2 py-1 text-right font-mono" x-text="money(advanceGivenTotal())"></td>
                                <td class="border border-gray-300 px-2 py-1"></td>
                                <td class="border border-gray-300 px-2 py-1"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <template x-for="(item, index) in items" :key="`item-hidden-${index}`">
                <div>
                    <input type="hidden" :name="`items[${index}][line_type]`" :value="item.line_type">
                    <input type="hidden" :name="`items[${index}][item_id]`" :value="item.item_id">
                    <input type="hidden" :name="`items[${index}][description]`" :value="item.description">
                    <input type="hidden" :name="`items[${index}][expense_category_id]`" :value="item.expense_category_id">
                    <input type="hidden" :name="`items[${index}][qty]`" :value="qty(item.qty)">
                    <input type="hidden" :name="`items[${index}][rate]`" :value="rate(item.rate)">
                </div>
            </template>

            <template x-for="(payment, index) in payments" :key="`payment-hidden-${index}`">
                <div>
                    <input type="hidden" :name="`payments[${index}][account_id]`" :value="payment.account_id">
                    <input type="hidden" :name="`payments[${index}][amount]`" :value="money(payment.amount)">
                    <input type="hidden" :name="`payments[${index}][cheque_number]`" :value="payment.cheque_number">
                </div>
            </template>

            <p x-show="message" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700" x-text="message"></p>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('purchases.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Purchase</button>
            </div>
        </form>
    </div>

    @include('partials.quick-item-entry')
    @include('partials.quick-expense-category-entry')

    <script>
        function transactionEntryForm(initialState) {
            return {
                partyId: String(initialState.partyId || ''),
                items: [],
                payments: [],
                itemsCatalog: initialState.itemsCatalog || [],
                expenseCategories: initialState.expenseCategories || [],
                itemsLookup: {},
                expenseCategoryLookup: {},
                defaultCashAccountId: String(initialState.defaultCashAccountId || ''),
                lineTypeStorageKey: String(initialState.lineTypeStorageKey || 'purchase-entry-line-type'),
                message: '',
                draftItem: {
                    line_type: 'general',
                    item_id: '',
                    description: '',
                    expense_category_id: '',
                    qty: 1,
                    rate: 0,
                    total: 0,
                    last_edited: 'rate',
                },
                draftPayment: {
                    account_id: String(initialState.defaultCashAccountId || ''),
                    amount: '',
                    cheque_number: '',
                },
                accountLookup: @js($accounts->mapWithKeys(fn ($account) => [$account->id => $account->name])->all()),
                init() {
                    this.itemsLookup = this.itemsCatalog.reduce((carry, item) => {
                        const id = String(item.id || '');
                        if (!id) {
                            return carry;
                        }

                        carry[id] = {
                            name: String(item.name || ''),
                            qty: this.toNumber(item.qty),
                            rate: this.toNumber(item.rate),
                            cost_price: this.toNumber(item.cost_price),
                        };

                        return carry;
                    }, {});

                    this.expenseCategoryLookup = this.expenseCategories.reduce((carry, category) => {
                        const id = String(category.id || '');
                        if (!id) {
                            return carry;
                        }

                        carry[id] = {
                            name: String(category.name || ''),
                            parent_id: category.parent_id ? String(category.parent_id) : null,
                        };

                        return carry;
                    }, {});

                    this.items = (initialState.items || [])
                        .map((item) => {
                            const lineType = ['item', 'general', 'expense'].includes(item.line_type) ? item.line_type : 'general';
                            const qty = this.toNumber(item.qty);
                            const rate = this.toNumber(item.rate);

                            return {
                                line_type: lineType,
                                item_id: lineType === 'item' ? String(item.item_id || '') : '',
                                description: lineType === 'general' ? String(item.description || '') : '',
                                expense_category_id: lineType === 'expense' ? String(item.expense_category_id || '') : '',
                                qty,
                                rate,
                                total: this.roundMoney(qty * rate),
                            };
                        })
                        .filter((item) => item.item_id || item.description || item.expense_category_id || item.qty > 0 || item.rate > 0);

                    this.payments = (initialState.payments || []).map((payment) => ({
                        account_id: String(payment.account_id || ''),
                        amount: this.toNumber(payment.amount),
                        cheque_number: String(payment.cheque_number || ''),
                    }));

                    this.restoreDraftLineType();
                    this.applyDraftLineType(false);

                    this.$nextTick(() => {
                        this.bindPartySelect();
                        this.syncItemSelect2();
                        window.addEventListener('quick-item-created', (event) => {
                            this.handleQuickItemCreated(event.detail || {});
                        });
                        window.addEventListener('quick-expense-category-created', (event) => {
                            this.handleQuickExpenseCategoryCreated(event.detail || {});
                        });
                        this.focusInitialField();
                    });
                },
                toNumber(value) {
                    const number = Number(value);

                    return Number.isFinite(number) ? number : 0;
                },
                roundMoney(value) {
                    return Math.round(this.toNumber(value) * 100) / 100;
                },
                money(value) {
                    return this.toNumber(value).toFixed(2);
                },
                rate(value) {
                    return this.toNumber(value).toFixed(4);
                },
                qty(value) {
                    return this.toNumber(value).toFixed(4);
                },
                focus(refName) {
                    this.$nextTick(() => {
                        this.$refs[refName]?.focus();
                    });
                },
                focusAndSelect(refName) {
                    this.$nextTick(() => {
                        const element = this.$refs[refName];
                        if (!element) {
                            return;
                        }

                        element.focus();
                        if (typeof element.select === 'function') {
                            element.select();
                        }
                    });
                },
                focusItemSelect() {
                    this.$nextTick(() => {
                        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && this.$refs.itemSelect) {
                            const $item = window.jQuery(this.$refs.itemSelect);
                            window.setTimeout(() => {
                                $item.select2('open');
                            }, 0);

                            return;
                        }

                        this.$refs.itemSelect?.focus();
                    });
                },
                selectInputValue(event) {
                    if (event.target && typeof event.target.select === 'function') {
                        event.target.select();
                    }
                },
                bindPartySelect() {
                    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !this.$refs.party) {
                        return;
                    }

                    const $party = window.jQuery(this.$refs.party);
                    $party.off('change.purchaseEntry');
                    $party.val(this.partyId).trigger('change.select2');
                    $party.on('change.purchaseEntry', (event) => {
                        this.partyId = String(event.target.value || '');
                    });
                },
                syncItemSelect2() {
                    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2 || !this.$refs.itemSelect) {
                        return;
                    }

                    const $item = window.jQuery(this.$refs.itemSelect);

                    if ($item.data('select2')) {
                        $item.off('change.purchaseEntry');
                        $item.select2('destroy');
                    }

                    $item.select2({ width: '100%' });
                    $item.val(this.draftItem.item_id || '').trigger('change.select2');
                    $item.on('change.purchaseEntry', (event) => {
                        this.draftItem.item_id = String(event.target.value || '');
                        this.onDraftItemSelected();
                    });
                },
                restoreDraftLineType() {
                    try {
                        const savedLineType = window.localStorage.getItem(this.lineTypeStorageKey);
                        if (['item', 'general', 'expense'].includes(savedLineType)) {
                            this.draftItem.line_type = savedLineType;
                        }
                    } catch (error) {
                        // Ignore localStorage access failures.
                    }
                },
                persistDraftLineType() {
                    try {
                        window.localStorage.setItem(this.lineTypeStorageKey, this.draftItem.line_type);
                    } catch (error) {
                        // Ignore localStorage access failures.
                    }
                },
                focusInitialField() {
                    if (this.draftItem.line_type === 'item') {
                        this.focusItemSelect();

                        return;
                    }

                    if (this.draftItem.line_type === 'expense') {
                        this.focus('expenseCategory');

                        return;
                    }

                    this.focus('itemDescription');
                },
                updateDraftItemFromRate() {
                    this.draftItem.last_edited = 'rate';
                    this.draftItem.total = this.roundMoney(this.toNumber(this.draftItem.qty) * this.toNumber(this.draftItem.rate));
                },
                updateDraftItemFromTotal() {
                    this.draftItem.last_edited = 'total';
                    this.draftItem.total = this.roundMoney(this.toNumber(this.draftItem.total));

                    if (this.toNumber(this.draftItem.qty) <= 0) {
                        this.draftItem.rate = 0;

                        return;
                    }

                    this.draftItem.rate = Math.round(this.toNumber(this.draftItem.total) / this.toNumber(this.draftItem.qty) * 10000) / 10000;
                },
                onDraftQtyChanged() {
                    if (this.draftItem.last_edited === 'total') {
                        this.updateDraftItemFromTotal();

                        return;
                    }

                    this.updateDraftItemFromRate();
                },
                applyDraftLineType(shouldFocus = true) {
                    const lineType = ['item', 'general', 'expense'].includes(this.draftItem.line_type)
                        ? this.draftItem.line_type
                        : 'general';

                    this.draftItem.line_type = lineType;
                    this.persistDraftLineType();

                    if (lineType === 'item') {
                        this.draftItem.description = '';
                        this.draftItem.expense_category_id = '';
                        this.updateDraftItemFromRate();
                        this.$nextTick(() => {
                            this.syncItemSelect2();
                            if (shouldFocus) {
                                this.focusItemSelect();
                            }
                        });

                        return;
                    }

                    if (lineType === 'expense') {
                        this.draftItem.item_id = '';
                        this.draftItem.description = '';
                        this.updateDraftItemFromRate();
                        if (shouldFocus) {
                            this.focus('expenseCategory');
                        }

                        return;
                    }

                    this.draftItem.item_id = '';
                    this.draftItem.expense_category_id = '';
                    this.updateDraftItemFromRate();
                    if (shouldFocus) {
                        this.focus('itemDescription');
                    }
                },
                onDraftLineTypeChanged() {
                    this.applyDraftLineType();
                },
                onDraftItemSelected() {
                    if (this.draftItem.line_type !== 'item') {
                        return;
                    }

                    const selectedItem = this.itemsLookup[String(this.draftItem.item_id)] || null;
                    if (!selectedItem) {
                        this.draftItem.rate = 0;
                        this.updateDraftItemFromRate();

                        return;
                    }

                    this.draftItem.rate = this.toNumber(selectedItem.cost_price || selectedItem.rate);
                    this.updateDraftItemFromRate();
                    this.focusAndSelect('itemQty');
                },
                lineTypeLabel(lineType) {
                    if (lineType === 'item') {
                        return 'Item';
                    }

                    if (lineType === 'expense') {
                        return 'Expense';
                    }

                    return 'General';
                },
                itemName(itemId) {
                    return this.itemsLookup[String(itemId)]?.name || 'Unknown Item';
                },
                expenseCategoryName(expenseCategoryId) {
                    return this.expenseCategoryLookup[String(expenseCategoryId)]?.name || 'Unknown Category';
                },
                lineLabel(item) {
                    if (item.line_type === 'item') {
                        return this.itemName(item.item_id);
                    }

                    if (item.line_type === 'expense') {
                        return this.expenseCategoryName(item.expense_category_id);
                    }

                    return String(item.description || 'General Line');
                },
                expenseCategoryCatalogJson() {
                    return JSON.stringify(this.expenseCategories.map((category) => ({
                        id: String(category.id),
                        name: String(category.name),
                    })));
                },
                upsertItemOption(item) {
                    if (!this.$refs.itemSelect) {
                        return;
                    }

                    const itemId = String(item.id || '');
                    const label = String(item.name || '');
                    const select = this.$refs.itemSelect;
                    let option = Array.from(select.options).find((existingOption) => existingOption.value === itemId);

                    if (!option) {
                        option = new Option(label, itemId, false, false);
                        select.add(option);

                        return;
                    }

                    option.text = label;
                },
                upsertExpenseCategoryOption(category) {
                    if (!this.$refs.expenseCategory) {
                        return;
                    }

                    const categoryId = String(category.id || '');
                    const label = String(category.name || '');
                    const select = this.$refs.expenseCategory;
                    let option = Array.from(select.options).find((existingOption) => existingOption.value === categoryId);

                    if (!option) {
                        option = new Option(label, categoryId, false, false);
                        select.add(option);

                        return;
                    }

                    option.text = label;
                },
                handleQuickItemCreated(item) {
                    const normalizedItem = {
                        id: String(item.id || ''),
                        name: String(item.name || ''),
                        qty: this.toNumber(item.qty),
                        rate: this.toNumber(item.rate),
                        cost_price: this.toNumber(item.cost_price),
                    };

                    if (!normalizedItem.id) {
                        return;
                    }

                    this.itemsLookup[normalizedItem.id] = normalizedItem;
                    this.itemsCatalog = [
                        ...this.itemsCatalog.filter((catalogItem) => String(catalogItem.id) !== normalizedItem.id),
                        normalizedItem,
                    ].sort((left, right) => String(left.name).localeCompare(String(right.name)));

                    this.upsertItemOption(normalizedItem);
                    this.draftItem.line_type = 'item';
                    this.draftItem.item_id = normalizedItem.id;
                    this.draftItem.description = '';
                    this.draftItem.expense_category_id = '';
                    this.persistDraftLineType();

                    this.$nextTick(() => {
                        this.syncItemSelect2();

                        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && this.$refs.itemSelect) {
                            window.jQuery(this.$refs.itemSelect).val(normalizedItem.id).trigger('change');

                            return;
                        }

                        this.onDraftItemSelected();
                    });
                },
                handleQuickExpenseCategoryCreated(category) {
                    const normalizedCategory = {
                        id: String(category.id || ''),
                        name: String(category.name || ''),
                        parent_id: category.parent_category_id ? String(category.parent_category_id) : null,
                    };

                    if (!normalizedCategory.id) {
                        return;
                    }

                    this.expenseCategoryLookup[normalizedCategory.id] = normalizedCategory;
                    this.expenseCategories = [
                        ...this.expenseCategories.filter((existingCategory) => String(existingCategory.id) !== normalizedCategory.id),
                        normalizedCategory,
                    ].sort((left, right) => String(left.name).localeCompare(String(right.name)));

                    this.upsertExpenseCategoryOption(normalizedCategory);
                    this.draftItem.line_type = 'expense';
                    this.draftItem.item_id = '';
                    this.draftItem.description = '';
                    this.draftItem.expense_category_id = normalizedCategory.id;
                    this.persistDraftLineType();
                    this.focusAndSelect('itemQty');
                },
                resetDraftItem(lineType = this.draftItem.line_type) {
                    this.draftItem = {
                        line_type: ['item', 'general', 'expense'].includes(lineType) ? lineType : 'general',
                        item_id: '',
                        description: '',
                        expense_category_id: '',
                        qty: 1,
                        rate: 0,
                        total: 0,
                        last_edited: 'rate',
                    };

                    this.applyDraftLineType(false);

                    if (this.draftItem.line_type === 'item') {
                        this.focusItemSelect();

                        return;
                    }

                    if (this.draftItem.line_type === 'expense') {
                        this.focus('expenseCategory');

                        return;
                    }

                    this.focus('itemDescription');
                },
                commitItem() {
                    this.message = '';

                    if (!this.partyId) {
                        this.message = 'Select party before adding line items.';
                        this.focus('party');

                        return;
                    }

                    const lineType = ['item', 'general', 'expense'].includes(this.draftItem.line_type)
                        ? this.draftItem.line_type
                        : 'general';
                    const itemId = String(this.draftItem.item_id || '');
                    const description = String(this.draftItem.description || '').trim();
                    const expenseCategoryId = String(this.draftItem.expense_category_id || '');
                    const qty = this.toNumber(this.draftItem.qty);
                    const rate = this.toNumber(this.draftItem.rate);

                    if (qty <= 0 || rate < 0) {
                        this.message = 'Quantity must be greater than zero and rate cannot be negative.';

                        return;
                    }

                    if (lineType === 'item' && !itemId) {
                        this.message = 'Select an item for item-type lines.';
                        this.focusItemSelect();

                        return;
                    }

                    if (lineType === 'general' && !description) {
                        this.message = 'Description is required for general lines.';
                        this.focus('itemDescription');

                        return;
                    }

                    if (lineType === 'expense' && !expenseCategoryId) {
                        this.message = 'Select an expense category for expense lines.';
                        this.focus('expenseCategory');

                        return;
                    }

                    this.items.push({
                        line_type: lineType,
                        item_id: lineType === 'item' ? itemId : '',
                        description: lineType === 'general' ? description : '',
                        expense_category_id: lineType === 'expense' ? expenseCategoryId : '',
                        qty,
                        rate,
                        total: this.roundMoney(qty * rate),
                    });

                    this.resetDraftItem(lineType);
                },
                commitItemFromTotal() {
                    const shouldReturnToItemSelect = this.draftItem.line_type === 'item';

                    this.commitItem();

                    if (!shouldReturnToItemSelect || this.message) {
                        return;
                    }

                    this.$nextTick(() => {
                        window.setTimeout(() => {
                            this.focusItemSelect();
                        }, 0);
                    });
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                },
                commitPayment() {
                    this.message = '';

                    if (!this.partyId) {
                        this.message = 'Select party before adding payment.';
                        this.focus('party');

                        return;
                    }

                    const accountId = String(this.draftPayment.account_id || '');
                    const amount = this.toNumber(this.draftPayment.amount);
                    const chequeNumber = String(this.draftPayment.cheque_number || '').trim();

                    if (!accountId || amount <= 0) {
                        this.message = 'Select payment account and amount.';

                        return;
                    }

                    this.payments.push({
                        account_id: accountId,
                        amount,
                        cheque_number: chequeNumber,
                    });

                    this.draftPayment = {
                        account_id: this.defaultCashAccountId,
                        amount: '',
                        cheque_number: '',
                    };

                    this.focus('paymentAccount');
                },
                removePayment(index) {
                    this.payments.splice(index, 1);
                },
                grandTotal() {
                    return this.items.reduce((sum, item) => sum + this.toNumber(item.total), 0);
                },
                paymentTotal() {
                    return this.payments.reduce((sum, payment) => sum + this.toNumber(payment.amount), 0);
                },
                dueTotal() {
                    return Math.max(0, this.grandTotal() - this.paymentTotal());
                },
                advanceGivenTotal() {
                    return Math.max(0, this.paymentTotal() - this.grandTotal());
                },
                accountName(accountId) {
                    return this.accountLookup[accountId] || 'Unknown account';
                },
                validateBeforeSubmit(event) {
                    this.message = '';

                    if (!this.partyId) {
                        event.preventDefault();
                        this.message = 'Party is required.';
                        this.focus('party');

                        return;
                    }

                    if (this.items.length === 0) {
                        event.preventDefault();
                        this.message = 'Add at least one line item before saving.';
                        this.focus('itemType');
                    }
                },
            };
        }
    </script>
@endsection
