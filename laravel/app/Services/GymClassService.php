<?php

namespace App\Services;

use App\Models\GymClass;
use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class GymClassService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $available = isset($filters['available']) ? (bool) $filters['available'] : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return GymClass::with(['coach', 'room'])->withCount('members')
            ->when(isset($filters['title']), fn ($q) => $q->where('title', 'like', "%{$filters['title']}%"))
            ->when(isset($filters['coach_id']), fn ($q) => $q->where('coach_id', $filters['coach_id']))
            ->when(isset($filters['room_id']), fn ($q) => $q->where('room_id', $filters['room_id']))
            ->when(isset($filters['class_time_from']), fn ($q) => $q->where('class_time', '>=', $filters['class_time_from']))
            ->when(isset($filters['class_time_to']), fn ($q) => $q->where('class_time', '<=', $filters['class_time_to']))
            ->when(isset($filters['capacity_min']), fn ($q) => $q->where('capacity', '>=', $filters['capacity_min']))
            ->when(isset($filters['capacity_max']), fn ($q) => $q->where('capacity', '<=', $filters['capacity_max']))
            ->when($available === true, fn ($q) => $q->whereRaw(
                '(SELECT COUNT(*) FROM registrations WHERE registrations.class_id = classes.class_id) < classes.capacity'
            ))
            ->when($available === false, fn ($q) => $q->whereRaw(
                '(SELECT COUNT(*) FROM registrations WHERE registrations.class_id = classes.class_id) >= classes.capacity'
            ))
            ->orderBy($filters['sort'] ?? 'class_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): GymClass
    {
        $this->assertFitsRoom($data['room_id'] ?? null, $data['capacity'] ?? null);

        return GymClass::create($data);
    }

    public function update(GymClass $class, array $data): GymClass
    {
        $roomId = array_key_exists('room_id', $data) ? $data['room_id'] : $class->room_id;
        $capacity = array_key_exists('capacity', $data) ? $data['capacity'] : $class->capacity;

        $this->assertFitsRoom($roomId, $capacity);

        $class->update($data);

        return $class;
    }

    public function delete(GymClass $class): void
    {
        $class->delete();
    }

    /**
     * A class cannot seat more members than the room it is scheduled in.
     */
    private function assertFitsRoom(?int $roomId, ?int $capacity): void
    {
        if (empty($roomId) || empty($capacity)) {
            return;
        }

        $room = Room::findOrFail($roomId);

        if ((int) $capacity > $room->capacity) {
            throw ValidationException::withMessages([
                'capacity' => "Class capacity ({$capacity}) exceeds room capacity ({$room->capacity}).",
            ]);
        }
    }
}
