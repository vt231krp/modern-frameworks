<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->role->isStaff()
            || $payment->subscription?->member?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->role->isStaff();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->role->isAdmin();
    }
}
