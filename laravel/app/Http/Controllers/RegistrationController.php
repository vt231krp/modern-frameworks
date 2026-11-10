<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    /**
     * Display a listing of registrations.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Registration::class);

        $filters = $request->validate([
            'member_id' => 'nullable|integer|exists:members,member_id',
            'class_id' => 'nullable|integer|exists:classes,class_id',
            'sort' => 'nullable|in:member_id,class_id',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->registrations->paginate($filters));
    }

    /**
     * Store a newly created registration (register member to a class).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Registration::class);

        $validated = $request->validate([
            'member_id' => 'required|integer|exists:members,member_id',
            'class_id' => 'required|integer|exists:classes,class_id',
        ]);

        /** @var User $user */
        $user = $request->user();

        $registration = $this->registrations->create($validated, $user);

        return response()->json($registration->load(['member', 'gymClass']), 201);
    }

    /**
     * Display a specific registration by composite keys.
     */
    public function show(int $memberId, int $classId): JsonResponse
    {
        $registration = $this->registrations->find($memberId, $classId);

        $this->authorize('view', $registration);

        return response()->json($registration);
    }

    /**
     * Move a registration to another class.
     */
    public function update(Request $request, int $memberId, int $classId): JsonResponse
    {
        $registration = $this->registrations->find($memberId, $classId);

        $this->authorize('update', $registration);

        $validated = $request->validate([
            'class_id' => 'required|integer|exists:classes,class_id',
        ]);

        /** @var User $user */
        $user = $request->user();

        $updated = $this->registrations->update(
            $memberId,
            $classId,
            (int) $validated['class_id'],
            $user
        );

        return response()->json($updated);
    }

    /**
     * Remove the specified registration.
     */
    public function destroy(Request $request, ?int $memberId = null, ?int $classId = null): JsonResponse
    {
        $mId = $memberId ?? $request->input('member_id');
        $cId = $classId ?? $request->input('class_id');

        if (! $mId || ! $cId) {
            return response()->json([
                'message' => 'Both member_id and class_id are required to delete a registration.',
            ], 422);
        }

        $registration = $this->registrations->find((int) $mId, (int) $cId);

        $this->authorize('delete', $registration);

        $this->registrations->delete((int) $mId, (int) $cId);

        return response()->json(['message' => 'Registration cancelled successfully']);
    }
}
