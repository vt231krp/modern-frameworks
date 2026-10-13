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
    public function index(): JsonResponse
    {
        $coaches = Coach::withCount('classes')->get();

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
