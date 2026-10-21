<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;

class RegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function view(User $user, Registration $registration): bool
    {
        return $user->role->isStaff() || $registration->member?->user_id === $user->id;
    }

    /**
     * Any authenticated user may register; ownership of member_id is
     * checked in RegistrationController::store().
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Registration $registration): bool
    {
        return false;
    }

    public function delete(User $user, Registration $registration): bool
    {
        return $user->role->isStaff() || $registration->member?->user_id === $user->id;
    }
}
