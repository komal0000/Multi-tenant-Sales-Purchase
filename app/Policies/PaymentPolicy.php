<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class PaymentPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->sameTenant($user, (int) $payment->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->sameTenant($user, (int) $payment->tenant_id);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->sameTenant($user, (int) $payment->tenant_id) && $user->isAdmin();
    }
}
