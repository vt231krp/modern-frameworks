<?php

namespace App\Policies;

use App\Models\GymClass;
use App\Models\User;

class GymClassPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GymClass $class): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, GymClass $class): bool
    {
        return $user->role->isStaff();
    }

    public function delete(User $user, GymClass $class): bool
    {
        return $user->role->isStaff();
    }
}
