<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Services\CoachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function __construct(private readonly CoachService $coaches)
    {
        $this->authorizeResource(Coach::class, 'coach');
    }

    /**
     * Display a listing of coaches.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'with_classes' => 'nullable|boolean',
            'sort' => 'nullable|in:coach_id,name',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->coaches->paginate($filters));
    }

    /**
     * Store a newly created coach.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $coach = $this->coaches->create($validated);

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
            'name' => 'sometimes|required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $this->coaches->update($coach, $validated);

        return response()->json($coach);
    }

    /**
     * Remove the specified coach.
     */
    public function destroy(Coach $coach): JsonResponse
    {
        $this->coaches->delete($coach);

        return response()->json(['message' => 'Coach deleted successfully']);
    }
}
