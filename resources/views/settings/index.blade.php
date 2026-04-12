@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Settings</h1>
            <p class="text-sm text-gray-500">Manage payroll rates and normal users.</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Payroll Settings</h2>
                <p class="mt-1 text-sm text-gray-500">Configure leave fine and overtime money used in salary sheet calculation.</p>

                <form method="POST" action="{{ route('settings.payroll.update') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="leave_fine_per_day" class="block text-sm font-medium text-gray-700">Leave Fine Per Day</label>
                        <input id="leave_fine_per_day" name="leave_fine_per_day" type="number" step="0.01" min="0" value="{{ old('leave_fine_per_day', $payrollSetting->leave_fine_per_day) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div>
                        <label for="overtime_money_per_day" class="block text-sm font-medium text-gray-700">Overtime Money Per Day</label>
                        <input id="overtime_money_per_day" name="overtime_money_per_day" type="number" step="0.01" min="0" value="{{ old('overtime_money_per_day', $payrollSetting->overtime_money_per_day) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Payroll Settings</button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Create Normal User</h2>
                <p class="mt-1 text-sm text-gray-500">Admin users can only be created via command. This form creates normal users only.</p>

                <form method="POST" action="{{ route('settings.users.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="normal_user_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input id="normal_user_name" name="name" type="text" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div>
                        <label for="normal_user_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input id="normal_user_phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="10" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div>
                        <label for="normal_user_email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
                        <input id="normal_user_email" name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div>
                        <label for="normal_user_password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="normal_user_password" name="password" type="password" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create Normal User</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Normal Users</h2>
                <span class="text-sm text-gray-500">Edit or delete normal users</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[860px] w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($normalUsers as $normalUser)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $normalUser->name }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $normalUser->phone }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $normalUser->email }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700"
                                            data-edit-user
                                            data-user-id="{{ $normalUser->id }}"
                                            data-user-name="{{ $normalUser->name }}"
                                            data-user-phone="{{ $normalUser->phone }}"
                                            data-user-email="{{ $normalUser->email }}"
                                        >
                                            Edit
                                        </button>
                                    <form method="POST" action="{{ route('settings.users.destroy', $normalUser) }}" onsubmit="return confirm('Delete this normal user?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-300 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">No normal users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="edit-user-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-gray-900/50" data-edit-user-modal-close></div>
            <div class="relative mx-auto mt-10 w-full max-w-2xl px-4 sm:mt-20">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Update Normal User</h3>
                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" data-edit-user-modal-close>Close</button>
                    </div>

                    <form id="edit-user-form" method="POST" action="#" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" id="edit_user_id" name="edit_user_id" value="{{ old('edit_user_id') }}">

                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input id="edit_name" name="edit_name" type="text" value="{{ old('edit_name') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>

                        <div>
                            <label for="edit_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input id="edit_phone" name="edit_phone" type="text" maxlength="10" value="{{ old('edit_phone') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>

                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
                            <input id="edit_email" name="edit_email" type="email" value="{{ old('edit_email') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>

                        <div>
                            <label for="edit_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input id="edit_password" name="edit_password" type="password" placeholder="Leave blank" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>

                        <div class="sm:col-span-2 flex items-center justify-end gap-2">
                            <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-edit-user-modal-close>Cancel</button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const modal = document.getElementById('edit-user-modal');
                const form = document.getElementById('edit-user-form');
                const updateRouteTemplate = @json(route('settings.users.update', ['user' => '__USER_ID__']));

                if (!modal || !form) {
                    return;
                }

                const fields = {
                    id: document.getElementById('edit_user_id'),
                    name: document.getElementById('edit_name'),
                    phone: document.getElementById('edit_phone'),
                    email: document.getElementById('edit_email'),
                    password: document.getElementById('edit_password'),
                };

                const openModal = (user) => {
                    fields.id.value = user.id || '';
                    fields.name.value = user.name || '';
                    fields.phone.value = user.phone || '';
                    fields.email.value = user.email || '';
                    fields.password.value = '';

                    form.action = updateRouteTemplate.replace('__USER_ID__', String(user.id || '0'));
                    modal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                };

                document.querySelectorAll('[data-edit-user]').forEach((button) => {
                    button.addEventListener('click', () => {
                        openModal({
                            id: button.dataset.userId,
                            name: button.dataset.userName,
                            phone: button.dataset.userPhone,
                            email: button.dataset.userEmail,
                        });
                    });
                });

                modal.querySelectorAll('[data-edit-user-modal-close]').forEach((button) => {
                    button.addEventListener('click', closeModal);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });

                const oldEditUserId = @json(old('edit_user_id'));
                if (oldEditUserId) {
                    openModal({
                        id: oldEditUserId,
                        name: @json(old('edit_name', '')),
                        phone: @json(old('edit_phone', '')),
                        email: @json(old('edit_email', '')),
                    });
                }
            })();
        </script>
    </div>
@endsection
