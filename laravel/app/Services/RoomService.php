<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RoomService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $withEquipment = isset($filters['with_equipment']) ? (bool) $filters['with_equipment'] : null;
        $withClasses = isset($filters['with_classes']) ? (bool) $filters['with_classes'] : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Room::withCount(['equipment', 'classes'])
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['capacity_min']), fn ($q) => $q->where('capacity', '>=', $filters['capacity_min']))
            ->when(isset($filters['capacity_max']), fn ($q) => $q->where('capacity', '<=', $filters['capacity_max']))
            ->when(! is_null($withEquipment), fn ($q) => $withEquipment ? $q->has('equipment') : $q->doesntHave('equipment'))
            ->when(! is_null($withClasses), fn ($q) => $withClasses ? $q->has('classes') : $q->doesntHave('classes'))
            ->orderBy($filters['sort'] ?? 'room_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Room
    {
        return Room::create($data);
    }

    public function update(Room $room, array $data): Room
    {
        if (array_key_exists('capacity', $data) && ! is_null($data['capacity'])) {
            $largestClass = (int) ($room->classes()->max('capacity') ?? 0);

            if ((int) $data['capacity'] < $largestClass) {
                throw ValidationException::withMessages([
                    'capacity' => "Room capacity cannot be lower than the largest scheduled class ({$largestClass}).",
                ]);
            }
        }

        $room->update($data);

        return $room;
    }

    public function delete(Room $room): void
    {
        $room->delete();
    }
}
