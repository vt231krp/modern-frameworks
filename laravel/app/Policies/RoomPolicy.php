<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Room $room): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, Room $room): bool
    {
        return $user->role->isStaff();
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->role->isAdmin();
    }
}
