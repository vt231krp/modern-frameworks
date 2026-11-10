<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $active = isset($filters['active']) ? (bool) $filters['active'] : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Subscription::with(['member', 'plan'])
            ->when(isset($filters['member_id']), fn ($q) => $q->where('member_id', $filters['member_id']))
            ->when(isset($filters['plan_id']), fn ($q) => $q->where('plan_id', $filters['plan_id']))
            ->when(isset($filters['price_min']), fn ($q) => $q->where('price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn ($q) => $q->where('price', '<=', $filters['price_max']))
            ->when(isset($filters['start_date_from']), fn ($q) => $q->whereDate('start_date', '>=', $filters['start_date_from']))
            ->when(isset($filters['start_date_to']), fn ($q) => $q->whereDate('start_date', '<=', $filters['start_date_to']))
            ->when(isset($filters['end_date_from']), fn ($q) => $q->whereDate('end_date', '>=', $filters['end_date_from']))
            ->when(isset($filters['end_date_to']), fn ($q) => $q->whereDate('end_date', '<=', $filters['end_date_to']))
            ->when($active === true, fn ($q) => $q->whereDate('end_date', '>=', now()->toDateString()))
            ->when($active === false, fn ($q) => $q->whereDate('end_date', '<', now()->toDateString()))
            ->orderBy($filters['sort'] ?? 'sub_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Subscription
    {
        return Subscription::create($this->applyPlan($data));
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($this->applyPlan($data, $subscription));

        return $subscription;
    }

    public function delete(Subscription $subscription): void
    {
        $subscription->delete();
    }

    /**
     * When a membership plan is chosen, derive the price and end date from it
     * unless the caller provided explicit values.
     */
    private function applyPlan(array $data, ?Subscription $subscription = null): array
    {
        if (empty($data['plan_id'])) {
            return $data;
        }

        $plan = MembershipPlan::findOrFail($data['plan_id']);

        if (! $plan->is_active && is_null($subscription)) {
            throw ValidationException::withMessages([
                'plan_id' => 'This membership plan is inactive.',
            ]);
        }

        if (! array_key_exists('price', $data) || is_null($data['price'])) {
            $data['price'] = $plan->price;
        }

        if (! array_key_exists('end_date', $data) || is_null($data['end_date'])) {
            $startDate = $data['start_date']
                ?? $subscription?->start_date?->format('Y-m-d')
                ?? now()->toDateString();

            $data['end_date'] = Carbon::parse($startDate)
                ->addDays($plan->duration_days)
                ->toDateString();
        }

        return $data;
    }
}
