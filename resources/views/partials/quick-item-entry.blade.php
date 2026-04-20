<div id="quick-item-entry-modal" class="fixed inset-0 z-[125] hidden overflow-y-auto" aria-hidden="true">
    <div class="absolute inset-0 bg-gray-900/50" data-quick-item-backdrop></div>

    <div class="relative flex min-h-full items-center justify-center px-4 py-4 sm:px-6 sm:py-8">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Quick Add Item</h2>
                <button type="button" data-quick-item-close class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            </div>

            <form id="quick-item-entry-form" action="{{ route('items.store') }}" method="POST" class="space-y-4 p-5">
                @csrf

                <div id="quick-item-errors" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

                <div>
                    <label for="quick_item_name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="quick_item_name" name="name" type="text" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="quick_item_cost_price" class="block text-sm font-medium text-gray-700">Cost Price</label>
                        <input id="quick_item_cost_price" name="cost_price" type="number" min="0" step="0.0001" required data-disable-wheel-change class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-right text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div>
                        <label for="quick_item_rate" class="block text-sm font-medium text-gray-700">Sell Rate</label>
                        <input id="quick_item_rate" name="rate" type="number" min="0" step="0.0001" required data-disable-wheel-change class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-right text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" data-quick-item-close class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="quick-item-submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.quickItemEntryInitialized) {
            return;
        }

        window.quickItemEntryInitialized = true;

        const modal = document.getElementById('quick-item-entry-modal');
        const form = document.getElementById('quick-item-entry-form');
        const errorsBox = document.getElementById('quick-item-errors');
        const submitButton = document.getElementById('quick-item-submit');

        if (!modal || !form || !errorsBox || !submitButton) {
            return;
        }

        function setModalOpen(isOpen) {
            modal.classList.toggle('hidden', !isOpen);
            modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.classList.toggle('overflow-hidden', isOpen);

            if (isOpen) {
                form.querySelector('#quick_item_name')?.focus();
            }
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
                messages.push('Could not save item. Please try again.');
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

        function closeQuickEntry() {
            clearErrors();
            form.reset();
            setModalOpen(false);
        }

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-open-quick-item-entry]')) {
                event.preventDefault();
                clearErrors();
                setModalOpen(true);
                return;
            }

            if (event.target.closest('[data-quick-item-close]') || event.target.closest('[data-quick-item-backdrop]')) {
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
                    renderErrors(payload.errors || { request: [payload.message || 'Could not save item. Please try again.'] });

                    return;
                }

                if (payload.item) {
                    window.dispatchEvent(new CustomEvent('quick-item-created', {
                        detail: payload.item,
                    }));
                }

                closeQuickEntry();
            } catch (error) {
                renderErrors({ request: ['Could not save item. Please check your connection and try again.'] });
            } finally {
                submitButton.disabled = false;
            }
        });
    })();
</script>
