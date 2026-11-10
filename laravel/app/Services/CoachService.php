<?php

namespace App\Services;

use App\Models\Coach;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CoachService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $withClasses = isset($filters['with_classes']) ? (bool) $filters['with_classes'] : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Coach::withCount('classes')
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['phone']), fn ($q) => $q->where('phone', 'like', "%{$filters['phone']}%"))
            ->when(! is_null($withClasses), fn ($q) => $withClasses ? $q->has('classes') : $q->doesntHave('classes'))
            ->orderBy($filters['sort'] ?? 'coach_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Coach
    {
        return Coach::create($data);
    }

    public function update(Coach $coach, array $data): Coach
    {
        $coach->update($data);

        return $coach;
    }

    public function delete(Coach $coach): void
    {
        $coach->delete();
    }
}
