<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSettingsUserRequest;
use App\Http\Requests\UpdatePayrollSettingsRequest;
use App\Http\Requests\UpdateSettingsUserRequest;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(): View
    {
        $payrollSetting = PayrollSetting::query()->firstOrCreate([], [
            'leave_fine_per_day' => 0,
            'overtime_money_per_day' => 0,
        ]);

        $tenantId = $this->tenantContext->requireId();

        $normalUsers = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 1)
            ->latest()
            ->get();

        return view('settings.index', [
            'payrollSetting' => $payrollSetting,
            'normalUsers' => $normalUsers,
        ]);
    }

    public function updatePayroll(UpdatePayrollSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $setting = PayrollSetting::query()->firstOrCreate([], [
            'leave_fine_per_day' => 0,
            'overtime_money_per_day' => 0,
        ]);

        $setting->update($validated);

        return redirect()
            ->route('settings.index')
            ->with('success', 'Payroll settings updated successfully.');
    }

    public function storeUser(StoreSettingsUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tenantId = $this->tenantContext->requireId();

        $email = filled($validated['email'] ?? null)
            ? $validated['email']
            : "user.{$validated['phone']}@ledger.local";

        if (User::query()->where('tenant_id', $tenantId)->where('email', $email)->exists()) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'email' => 'The generated email is already in use. Please enter a unique email.',
                ]);
        }

        User::query()->create([
            'name' => $validated['name'],
            'phone' => (int) $validated['phone'],
            'email' => $email,
            'password' => $validated['password'],
            'role' => 1,
            'tenant_id' => $tenantId,
        ]);

        return redirect()
            ->route('settings.index')
            ->with('success', 'Normal user created successfully.');
    }

    public function updateUser(UpdateSettingsUserRequest $request, User $user): RedirectResponse
    {
        abort_unless((int) $user->tenant_id === $this->tenantContext->requireId(), 404);

        if ((int) $user->role === 0) {
            abort(403, 'Admin users cannot be edited from settings.');
        }

        $validated = $request->validated();

        abort_if((int) $validated['edit_user_id'] !== (int) $user->id, 422, 'The selected user does not match the edit request.');

        $email = filled($validated['edit_email'] ?? null)
            ? $validated['edit_email']
            : "user.{$validated['edit_phone']}@ledger.local";

        $emailExists = User::query()
            ->where('tenant_id', $this->tenantContext->requireId())
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailExists) {
            return back()
                ->withInput($request->except('edit_password'))
                ->withErrors([
                    'edit_email' => 'The generated email is already in use. Please enter a unique email.',
                ]);
        }

        $payload = [
            'name' => $validated['edit_name'],
            'phone' => (int) $validated['edit_phone'],
            'email' => $email,
            'role' => 1,
        ];

        if (filled($validated['edit_password'] ?? null)) {
            $payload['password'] = $validated['edit_password'];
        }

        $user->update($payload);

        return redirect()
            ->route('settings.index')
            ->with('success', 'Normal user updated successfully.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        abort_unless((int) $user->tenant_id === $this->tenantContext->requireId(), 404);

        if ((int) $user->role === 0) {
            abort(403, 'Admin users cannot be deleted from settings.');
        }

        $currentUser = Auth::user();

        if ($currentUser instanceof User && (int) $currentUser->id === (int) $user->id) {
            return redirect()
                ->route('settings.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('settings.index')
            ->with('success', 'Normal user deleted successfully.');
    }
}
