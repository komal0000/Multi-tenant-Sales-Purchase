<div id="quick-party-entry-modal" class="fixed inset-0 z-[120] hidden overflow-y-auto" aria-hidden="true">
    <div class="absolute inset-0 bg-gray-900/50" data-quick-party-backdrop></div>

    <div class="relative flex min-h-full items-center justify-center px-4 py-4 sm:px-6 sm:py-8">
        <div class="w-full max-w-xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-4rem)]">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Quick Party Entry</h2>
                <button type="button" data-quick-party-close class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            </div>

            <form id="quick-party-entry-form" action="{{ route('parties.store') }}" method="POST" class="space-y-5 overflow-y-auto p-5">
                @csrf

                <div id="quick-party-errors" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

                <div>
                    <label for="quick_party_name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="quick_party_name" name="name" type="text" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                </div>

                <div>
                    <label for="quick_party_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input id="quick_party_phone" name="phone" type="tel" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm placeholder-gray-400 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Optional">
                </div>

                <div>
                    <label for="quick_party_address" class="block text-sm font-medium text-gray-700">Address</label>
                    <input id="quick_party_address" name="address" type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm placeholder-gray-400 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Optional">
                </div>

                <div id="quick-party-opening-fields" class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="quick_party_opening_balance" class="block text-sm font-medium text-gray-700">Opening Balance</label>
                        <input id="quick_party_opening_balance" name="opening_balance" type="number" min="0" step="0.01" value="0" data-disable-wheel-change class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div>
                        @include('partials.bs-date-selector', ['name' => 'opening_balance_date_bs', 'label' => 'Opening BS Date', 'value' => \App\Helpers\DateHelper::getCurrentBS()])
                    </div>
                    <div>
                        <label for="quick_party_opening_balance_side" class="block text-sm font-medium text-gray-700">Balance Type</label>
                        <select id="quick_party_opening_balance_side" name="opening_balance_side" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                            <option value="dr" selected>Receivable</option>
                            <option value="cr">Payable</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" data-quick-party-close class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="quick-party-submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Party</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.quickPartyEntryInitialized) {
            return;
        }

        window.quickPartyEntryInitialized = true;

        const modal = document.getElementById('quick-party-entry-modal');
        const form = document.getElementById('quick-party-entry-form');
        const errorsBox = document.getElementById('quick-party-errors');
        const submitButton = document.getElementById('quick-party-submit');
        const phoneInput = document.getElementById('quick_party_phone');
        const openingFields = document.getElementById('quick-party-opening-fields');

        if (!modal || !form || !errorsBox || !submitButton || !phoneInput || !openingFields) {
            return;
        }

        let targetSelectId = null;
        let postSaveAction = null;
        let hideOpeningFields = false;

        const defaultState = {
            opening_balance: '0',
            opening_balance_side: 'dr',
            opening_balance_date_bs: @json(\App\Helpers\DateHelper::getCurrentBS()),
        };

        function setModalOpen(isOpen) {
            if (isOpen) {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
                const nameInput = form.querySelector('#quick_party_name');
                if (nameInput) {
                    nameInput.focus();
                }
                return;
            }

            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        }

        function clearErrors() {
            errorsBox.classList.add('hidden');
            errorsBox.innerHTML = '';
        }

        function renderErrors(errorPayload) {
            const messages = [];
            Object.values(errorPayload || {}).forEach((value) => {
                if (Array.isArray(value)) {
                    value.forEach((item) => messages.push(String(item)));
                } else if (value) {
                    messages.push(String(value));
                }
            });

            if (!messages.length) {
                messages.push('Could not save party. Please try again.');
            }

            const list = document.createElement('ul');
            list.className = 'list-disc space-y-1 pl-5';

            messages.forEach((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            });

            errorsBox.replaceChildren(list);
            errorsBox.classList.remove('hidden');
        }

        function resetForm() {
            form.reset();
            const openingInput = form.querySelector('[name="opening_balance"]');
            const sideSelect = form.querySelector('[name="opening_balance_side"]');
            const dateInput = form.querySelector('[name="opening_balance_date_bs"]');

            if (openingInput) {
                openingInput.value = defaultState.opening_balance;
            }

            if (sideSelect) {
                sideSelect.value = defaultState.opening_balance_side;
            }

            if (dateInput) {
                dateInput.value = defaultState.opening_balance_date_bs;
            }

            phoneInput.value = '';
        }

        function setOpeningFieldsVisibility(shouldHide) {
            hideOpeningFields = shouldHide;
            openingFields.classList.toggle('hidden', shouldHide);

            openingFields.querySelectorAll('input, select, textarea, button').forEach((field) => {
                if (!field.name) {
                    return;
                }

                field.disabled = shouldHide;
            });

            if (shouldHide) {
                resetForm();
            }
        }

        function formatPartyLabel(party) {
            const parts = [party.name];

            if (party.phone) {
                parts.push(party.phone);
            }

            if (party.address) {
                parts.push(party.address);
            }

            return parts.join(' • ');
        }

        function updateTargetSelect(party) {
            if (!targetSelectId) {
                return;
            }

            const select = document.getElementById(targetSelectId);
            if (!select) {
                return;
            }

            const partyId = String(party.id);
            const partyLabel = formatPartyLabel(party);

            let option = Array.from(select.options).find((item) => item.value === partyId);

            if (!option) {
                option = new Option(partyLabel, partyId, true, true);
                select.add(option);
            } else {
                option.text = partyLabel;
                option.selected = true;
            }

            select.value = partyId;

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                const $select = window.jQuery(select);
                $select.trigger('change');
            } else {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function openQuickEntry(trigger) {
            targetSelectId = trigger.getAttribute('data-party-select-id') || null;
            postSaveAction = trigger.getAttribute('data-party-post-save') || null;
            setOpeningFieldsVisibility(trigger.getAttribute('data-quick-party-hide-opening') === 'true');

            clearErrors();
            setModalOpen(true);
        }

        function closeQuickEntry() {
            clearErrors();
            setModalOpen(false);
            targetSelectId = null;
            postSaveAction = null;
            setOpeningFieldsVisibility(false);
        }

        document.addEventListener('click', (event) => {
            const openTrigger = event.target.closest('[data-open-quick-party-entry]');
            if (openTrigger) {
                event.preventDefault();
                openQuickEntry(openTrigger);
                return;
            }

            const closeTrigger = event.target.closest('[data-quick-party-close]');
            const backdropTrigger = event.target.closest('[data-quick-party-backdrop]');

            if (closeTrigger || backdropTrigger) {
                event.preventDefault();
                closeQuickEntry();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeQuickEntry();
            }
        });

        form.querySelectorAll('[data-disable-wheel-change]').forEach((input) => {
            input.addEventListener('wheel', (event) => {
                event.preventDefault();
            }, { passive: false });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            phoneInput.value = phoneInput.value.trim();

            submitButton.disabled = true;

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
                    if (response.status === 422 && payload.errors) {
                        renderErrors(payload.errors);
                    } else {
                        renderErrors({ request: [payload.message || 'Could not save party. Please try again.'] });
                    }

                    return;
                }

                if (payload.party) {
                    updateTargetSelect(payload.party);
                }

                const shouldReload = postSaveAction === 'reload';

                resetForm();
                closeQuickEntry();

                if (shouldReload) {
                    window.location.reload();
                }
            } catch (error) {
                renderErrors({ request: ['Could not save party. Please check your connection and try again.'] });
            } finally {
                submitButton.disabled = false;
            }
        });
    })();
</script>
