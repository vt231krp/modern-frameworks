<?php

namespace App\Http\Controllers;

use App\Models\GymClass;
use App\Services\GymClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GymClassController extends Controller
{
    public function __construct(private readonly GymClassService $classes)
    {
        $this->authorizeResource(GymClass::class, 'class');
    }

    /**
     * Display a listing of classes.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'title' => 'nullable|string|max:100',
            'coach_id' => 'nullable|integer|exists:coaches,coach_id',
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'class_time_from' => 'nullable|date',
            'class_time_to' => ['nullable', 'date', Rule::when(
                $request->filled('class_time_from'),
                'after_or_equal:class_time_from'
            )],
            'capacity_min' => 'nullable|integer|min:0',
            'capacity_max' => 'nullable|integer|min:0',
            'available' => 'nullable|boolean',
            'sort' => 'nullable|in:class_id,title,class_time,capacity',            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->classes->paginate($filters));
    }

    /**
     * Store a newly created class.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'coach_id' => 'nullable|integer|exists:coaches,coach_id',
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'class_time' => 'required|date',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $class = $this->classes->create($validated);

        return response()->json($class->load(['coach', 'room']), 201);
    }

    /**
     * Display the specified class.
     */
    public function show(GymClass $class): JsonResponse
    {
        $class->load(['coach', 'room', 'members']);

        return response()->json($class);
    }

    /**
     * Update the specified class.
     */
    public function update(Request $request, GymClass $class): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:100',
            'coach_id' => 'nullable|integer|exists:coaches,coach_id',
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'class_time' => 'sometimes|required|date',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $this->classes->update($class, $validated);

        return response()->json($class->load(['coach', 'room']));
    }

    /**
     * Remove the specified class.
     */
    public function destroy(GymClass $class): JsonResponse
    {
        $this->classes->delete($class);

        return response()->json(['message' => 'Class deleted successfully']);
    }
}
