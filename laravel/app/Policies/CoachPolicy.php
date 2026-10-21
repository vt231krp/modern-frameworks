<?php

namespace App\Policies;

use App\Models\Coach;
use App\Models\User;

class CoachPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Coach $coach): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, Coach $coach): bool
    {
        return $user->role->isStaff();
    }

    public function delete(User $user, Coach $coach): bool
    {
        return $user->role->isAdmin();
    }
}
