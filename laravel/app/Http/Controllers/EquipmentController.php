<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Services\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    private const CONDITIONS = ['excellent', 'good', 'fair', 'broken'];

    public function __construct(private readonly EquipmentService $equipment)
    {
        $this->authorizeResource(Equipment::class, 'equipment');
    }

    /**
     * Display a listing of equipment.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name' => 'nullable|string|max:100',
            'condition' => ['nullable', Rule::in(self::CONDITIONS)],
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'quantity_min' => 'nullable|integer|min:0',
            'sort' => 'nullable|in:equipment_id,name,quantity,condition',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->equipment->paginate($filters));
    }

    /**
     * Store a newly created equipment item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'quantity' => 'nullable|integer|min:0',
            'condition' => ['nullable', Rule::in(self::CONDITIONS)],
            'room_id' => 'nullable|integer|exists:rooms,room_id',
        ]);

        $equipment = $this->equipment->create($validated);

        return response()->json($equipment->load('room'), 201);
    }

    /**
     * Display the specified equipment item.
     */
    public function show(Equipment $equipment): JsonResponse
    {
        $equipment->load('room');

        return response()->json($equipment);
    }

    /**
     * Update the specified equipment item.
     */
    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'quantity' => 'nullable|integer|min:0',
            'condition' => ['nullable', Rule::in(self::CONDITIONS)],
            'room_id' => 'nullable|integer|exists:rooms,room_id',
        ]);

        $this->equipment->update($equipment, $validated);

        return response()->json($equipment->load('room'));
    }

    /**
     * Remove the specified equipment item.
     */
    public function destroy(Equipment $equipment): JsonResponse
    {
        $this->equipment->delete($equipment);

        return response()->json(['message' => 'Equipment deleted successfully']);
    }
}
