<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function __construct(private readonly RoomService $rooms)
    {
        $this->authorizeResource(Room::class, 'room');
    }

    /**
     * Display a listing of rooms.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name' => 'nullable|string|max:100',
            'capacity_min' => 'nullable|integer|min:0',
            'capacity_max' => 'nullable|integer|min:0',
            'with_equipment' => 'nullable|boolean',
            'with_classes' => 'nullable|boolean',
            'sort' => 'nullable|in:room_id,name,capacity',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->rooms->paginate($filters));
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:rooms,name',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $room = $this->rooms->create($validated);

        return response()->json($room, 201);
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): JsonResponse
    {
        $room->load(['equipment', 'classes.coach']);

        return response()->json($room);
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('rooms', 'name')->ignore($room->room_id, 'room_id'),
            ],
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $this->rooms->update($room, $validated);

        return response()->json($room);
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Room $room): JsonResponse
    {
        $this->rooms->delete($room);

        return response()->json(['message' => 'Room deleted successfully']);
    }
}
