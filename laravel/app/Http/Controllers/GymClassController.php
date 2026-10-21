<?php

namespace App\Http\Controllers;

use App\Models\GymClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GymClassController extends Controller
{
    /**
     * Display a listing of classes.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'title'           => 'nullable|string|max:100',
            'coach_id'        => 'nullable|integer|exists:coaches,coach_id',
            'class_time_from' => 'nullable|date',
            'class_time_to'   => ['nullable', 'date', Rule::when(
                $request->filled('class_time_from'),
                'after_or_equal:class_time_from'
            )],
            'capacity_min'    => 'nullable|integer|min:0',
            'capacity_max'    => 'nullable|integer|min:0',
            'available'       => 'nullable|boolean',
            'sort'            => 'nullable|in:class_id,title,class_time,capacity',
            'direction'       => 'nullable|in:asc,desc',
            'per_page'        => 'nullable|integer|min:1|max:100',
        ]);

        $available = $request->filled('available') ? $request->boolean('available') : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $classes = GymClass::with('coach')->withCount('members')
            ->when(isset($filters['title']), fn ($q) => $q->where('title', 'like', "%{$filters['title']}%"))
            ->when(isset($filters['coach_id']), fn ($q) => $q->where('coach_id', $filters['coach_id']))
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

        return response()->json($classes);
    }

    /**
     * Store a newly created class.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:100',
            'coach_id'   => 'nullable|integer|exists:coaches,coach_id',
            'class_time' => 'required|date',
            'capacity'   => 'nullable|integer|min:1',
        ]);

        $class = GymClass::create($validated);

        return response()->json($class->load('coach'), 201);
    }

    /**
     * Display the specified class.
     */
    public function show(GymClass $class): JsonResponse
    {
        $class->load(['coach', 'members']);

        return response()->json($class);
    }

    /**
     * Update the specified class.
     */
    public function update(Request $request, GymClass $class): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'sometimes|required|string|max:100',
            'coach_id'   => 'nullable|integer|exists:coaches,coach_id',
            'class_time' => 'sometimes|required|date',
            'capacity'   => 'nullable|integer|min:1',
        ]);

        $class->update($validated);

        return response()->json($class->load('coach'));
    }

    /**
     * Remove the specified class.
     */
    public function destroy(GymClass $class): JsonResponse
    {
        $class->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }
}
