<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    /**
     * Display a listing of coaches.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name'         => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'with_classes' => 'nullable|boolean',
            'sort'         => 'nullable|in:coach_id,name',
            'direction'    => 'nullable|in:asc,desc',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $withClasses = $request->filled('with_classes') ? $request->boolean('with_classes') : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $coaches = Coach::withCount('classes')
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['phone']), fn ($q) => $q->where('phone', 'like', "%{$filters['phone']}%"))
            ->when(!is_null($withClasses), fn ($q) => $withClasses ? $q->has('classes') : $q->doesntHave('classes'))
            ->orderBy($filters['sort'] ?? 'coach_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);

        return response()->json($coaches);
    }

    /**
     * Store a newly created coach.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $coach = Coach::create($validated);

        return response()->json($coach, 201);
    }

    /**
     * Display the specified coach.
     */
    public function show(Coach $coach): JsonResponse
    {
        $coach->load('classes');

        return response()->json($coach);
    }

    /**
     * Update the specified coach.
     */
    public function update(Request $request, Coach $coach): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $coach->update($validated);

        return response()->json($coach);
    }

    /**
     * Remove the specified coach.
     */
    public function destroy(Coach $coach): JsonResponse
    {
        $coach->delete();

        return response()->json(['message' => 'Coach deleted successfully']);
    }
}
