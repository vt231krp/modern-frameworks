<?php

namespace App\Policies;

use App\Models\MembershipPlan;
use App\Models\User;

class MembershipPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MembershipPlan $plan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->isStaff();
    }

    public function update(User $user, MembershipPlan $plan): bool
    {
        return $user->role->isStaff();
    }

    public function delete(User $user, MembershipPlan $plan): bool
    {
        return $user->role->isAdmin();
    }
}
