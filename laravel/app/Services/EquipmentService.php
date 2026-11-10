<?php

namespace App\Services;

use App\Models\Equipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EquipmentService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Equipment::with('room')
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['condition']), fn ($q) => $q->where('condition', $filters['condition']))
            ->when(isset($filters['room_id']), fn ($q) => $q->where('room_id', $filters['room_id']))
            ->when(isset($filters['quantity_min']), fn ($q) => $q->where('quantity', '>=', $filters['quantity_min']))
            ->orderBy($filters['sort'] ?? 'equipment_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Equipment
    {
        return Equipment::create($data);
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        $equipment->update($data);

        return $equipment;
    }

    public function delete(Equipment $equipment): void
    {
        $equipment->delete();
    }
}
