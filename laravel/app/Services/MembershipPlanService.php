<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MembershipPlanService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $active = isset($filters['is_active']) ? (bool) $filters['is_active'] : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return MembershipPlan::withCount('subscriptions')
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['price_min']), fn ($q) => $q->where('price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn ($q) => $q->where('price', '<=', $filters['price_max']))
            ->when(isset($filters['duration_min']), fn ($q) => $q->where('duration_days', '>=', $filters['duration_min']))
            ->when(isset($filters['duration_max']), fn ($q) => $q->where('duration_days', '<=', $filters['duration_max']))
            ->when(! is_null($active), fn ($q) => $q->where('is_active', $active))
            ->orderBy($filters['sort'] ?? 'plan_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): MembershipPlan
    {
        return MembershipPlan::create($data);
    }

    public function update(MembershipPlan $plan, array $data): MembershipPlan
    {
        $plan->update($data);

        return $plan;
    }

    public function delete(MembershipPlan $plan): void
    {
        $plan->delete();
    }
}
