<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function view(User $user, Member $member): bool
    {
        return $user->role->isStaff() || $member->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, Member $member): bool
    {
        return $user->role->isStaff() || $member->user_id === $user->id;
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->role->isAdmin();
    }
}
