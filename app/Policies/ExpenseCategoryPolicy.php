<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ExpenseCategoryPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->sameTenant($user, (int) $expenseCategory->tenant_id);
    }

    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->sameTenant($user, (int) $expenseCategory->tenant_id);
    }

    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->sameTenant($user, (int) $expenseCategory->tenant_id);
    }
}
