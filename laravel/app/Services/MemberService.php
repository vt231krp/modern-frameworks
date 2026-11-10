<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MemberService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $active = isset($filters['active']) ? (bool) $filters['active'] : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Member::withCount(['subscriptions', 'classes'])
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['phone']), fn ($q) => $q->where('phone', 'like', "%{$filters['phone']}%"))
            ->when(isset($filters['join_date_from']), fn ($q) => $q->whereDate('join_date', '>=', $filters['join_date_from']))
            ->when(isset($filters['join_date_to']), fn ($q) => $q->whereDate('join_date', '<=', $filters['join_date_to']))
            ->when($active === true, fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->where('end_date', '>=', now()->toDateString())))
            ->when($active === false, fn ($q) => $q->whereDoesntHave('subscriptions', fn ($s) => $s->where('end_date', '>=', now()->toDateString())))
            ->orderBy($filters['sort'] ?? 'member_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Member
    {
        // Keep the database default for join_date when the client omits it.
        $data = array_filter($data, fn ($value) => ! is_null($value));

        return Member::create($data);
    }

    public function update(Member $member, array $data): Member
    {
        $member->update($data);

        return $member;
    }

    public function delete(Member $member): void
    {
        $member->delete();
    }
}
