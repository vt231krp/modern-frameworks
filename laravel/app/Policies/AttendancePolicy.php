<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->role->isStaff() || $attendance->member?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->role->isStaff();
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->role->isAdmin();
    }
}
