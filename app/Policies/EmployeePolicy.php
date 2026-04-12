<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class EmployeePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->sameTenant($user, (int) $employee->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->sameTenant($user, (int) $employee->tenant_id);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->sameTenant($user, (int) $employee->tenant_id) && $user->isAdmin();
    }
}
