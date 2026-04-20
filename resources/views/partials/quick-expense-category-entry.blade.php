<div id="quick-expense-category-modal" class="fixed inset-0 z-[125] hidden overflow-y-auto" aria-hidden="true">
    <div class="absolute inset-0 bg-gray-900/50" data-quick-expense-category-backdrop></div>

    <div class="relative flex min-h-full items-center justify-center px-4 py-4 sm:px-6 sm:py-8">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Quick Add Category</h2>
                <button type="button" data-quick-expense-category-close class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            </div>

            <form id="quick-expense-category-form" action="{{ route('expense-categories.store') }}" method="POST" class="space-y-4 p-5">
                @csrf

                <div id="quick-expense-category-errors" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

                <div>
                    <label for="quick_expense_category_name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="quick_expense_category_name" name="name" type="text" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                </div>

                <div>
                    <label for="quick_expense_parent_category_id" class="block text-sm font-medium text-gray-700">Parent Category</label>
                    <select id="quick_expense_parent_category_id" name="parent_category_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="">No parent</option>
                    </select>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" data-quick-expense-category-close class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="quick-expense-category-submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.quickExpenseCategoryEntryInitialized) {
            return;
        }

        window.quickExpenseCategoryEntryInitialized = true;

        const modal = document.getElementById('quick-expense-category-modal');
        const form = document.getElementById('quick-expense-category-form');
        const errorsBox = document.getElementById('quick-expense-category-errors');
        const submitButton = document.getElementById('quick-expense-category-submit');
        const parentSelect = document.getElementById('quick_expense_parent_category_id');

        if (!modal || !form || !errorsBox || !submitButton || !parentSelect) {
            return;
        }

        function setModalOpen(isOpen) {
            modal.classList.toggle('hidden', !isOpen);
            modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.classList.toggle('overflow-hidden', isOpen);

            if (isOpen) {
                form.querySelector('#quick_expense_category_name')?.focus();
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
                messages.push('Could not save category. Please try again.');
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
            parentSelect.innerHTML = '<option value="">No parent</option>';
        }

        function syncParentOptions() {
            const detail = window.quickExpenseCategoryModalContext || {};
            const categories = Array.isArray(detail.categories) ? detail.categories : [];
            const selectedParentId = String(detail.selectedParentId || '');

            parentSelect.innerHTML = '<option value="">No parent</option>';

            categories.forEach((category) => {
                const option = new Option(category.name, String(category.id), false, String(category.id) === selectedParentId);
                parentSelect.add(option);
            });
        }

        function closeQuickEntry() {
            clearErrors();
            resetForm();
            setModalOpen(false);
            window.quickExpenseCategoryModalContext = null;
        }

        document.addEventListener('click', (event) => {
            const openTrigger = event.target.closest('[data-open-quick-expense-category-entry]');
            if (openTrigger) {
                event.preventDefault();
                let categories = [];

                try {
                    categories = JSON.parse(openTrigger.getAttribute('data-expense-categories') || '[]');
                } catch (error) {
                    categories = [];
                }

                window.quickExpenseCategoryModalContext = {
                    categories: Array.isArray(categories) ? categories : [],
                    selectedParentId: openTrigger.getAttribute('data-default-parent-category-id') || '',
                };
                syncParentOptions();
                clearErrors();
                setModalOpen(true);
                return;
            }

            if (event.target.closest('[data-quick-expense-category-close]') || event.target.closest('[data-quick-expense-category-backdrop]')) {
                event.preventDefault();
                closeQuickEntry();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeQuickEntry();
            }
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
                    renderErrors(payload.errors || { request: [payload.message || 'Could not save category. Please try again.'] });

                    return;
                }

                if (payload.category) {
                    window.dispatchEvent(new CustomEvent('quick-expense-category-created', {
                        detail: payload.category,
                    }));
                }

                closeQuickEntry();
            } catch (error) {
                renderErrors({ request: ['Could not save category. Please check your connection and try again.'] });
            } finally {
                submitButton.disabled = false;
            }
        });
    })();
</script>
