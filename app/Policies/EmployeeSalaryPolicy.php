<?php

namespace App\Policies;

use App\Models\EmployeeSalary;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class EmployeeSalaryPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, EmployeeSalary $employeeSalary): bool
    {
        return $this->sameTenant($user, (int) $employeeSalary->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, EmployeeSalary $employeeSalary): bool
    {
        return $this->sameTenant($user, (int) $employeeSalary->tenant_id);
    }

    public function delete(User $user, EmployeeSalary $employeeSalary): bool
    {
        return $this->sameTenant($user, (int) $employeeSalary->tenant_id) && $user->isAdmin();
    }
}
