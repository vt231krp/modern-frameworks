<?php

namespace App\Http\Controllers;

use App\Models\GymClass;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    /**
     * Display a listing of registrations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Registration::with(['member', 'gymClass']);

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->query('member_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created registration (register member to a class).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => 'required|integer|exists:members,member_id',
            'class_id'  => 'required|integer|exists:classes,class_id',
        ]);

        $alreadyRegistered = Registration::where('member_id', $validated['member_id'])
            ->where('class_id', $validated['class_id'])
            ->exists();

        if ($alreadyRegistered) {
            return response()->json([
                'message' => 'Member is already registered for this class.',
            ], 422);
        }

        $class = GymClass::findOrFail($validated['class_id']);
        $currentRegistrations = Registration::where('class_id', $class->class_id)->count();

        if ($currentRegistrations >= $class->capacity) {
            return response()->json([
                'message' => 'This class has already reached its maximum capacity (' . $class->capacity . ').',
            ], 422);
        }

        $registration = Registration::create($validated);

        return response()->json($registration->load(['member', 'gymClass']), 201);
    }

    /**
     * Display a specific registration by composite keys.
     */
    public function show(int $memberId, int $classId): JsonResponse
    {
        $registration = Registration::with(['member', 'gymClass'])
            ->where('member_id', $memberId)
            ->where('class_id', $classId)
            ->firstOrFail();

        return response()->json($registration);
    }

    /**
     * Remove the specified registration.
     */
    public function destroy(Request $request, ?int $memberId = null, ?int $classId = null): JsonResponse
    {
        $mId = $memberId ?? $request->input('member_id');
        $cId = $classId ?? $request->input('class_id');

        if (!$mId || !$cId) {
            return response()->json([
                'message' => 'Both member_id and class_id are required to delete a registration.',
            ], 422);
        }

        $deleted = Registration::where('member_id', $mId)
            ->where('class_id', $cId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Registration not found.',
            ], 404);
        }

        return response()->json(['message' => 'Registration cancelled successfully']);
    }
}
