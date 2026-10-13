<?php

namespace App\Http\Controllers;

use App\Models\GymClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymClassController extends Controller
{
    /**
     * Display a listing of classes.
     */
    public function index(): JsonResponse
    {
        $classes = GymClass::with('coach')->withCount('members')->get();

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
