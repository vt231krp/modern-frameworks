<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late'];

    public function __construct(private readonly AttendanceService $attendances)
    {
        $this->authorizeResource(Attendance::class, 'attendance');
    }

    /**
     * Display a listing of attendance records.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'member_id' => 'nullable|integer|exists:members,member_id',
            'class_id' => 'nullable|integer|exists:classes,class_id',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'checked_in_from' => 'nullable|date',
            'checked_in_to' => ['nullable', 'date', Rule::when(
                $request->filled('checked_in_from'),
                'after_or_equal:checked_in_from'
            )],
            'sort' => 'nullable|in:attendance_id,status,checked_in_at',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->attendances->paginate($filters));
    }

    /**
     * Record attendance for a member registered to a class.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => 'required|integer|exists:members,member_id',
            'class_id' => 'required|integer|exists:classes,class_id',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'checked_in_at' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $attendance = $this->attendances->create($validated);

        return response()->json($attendance->load(['member', 'gymClass']), 201);
    }

    /**
     * Display the specified attendance record.
     */
    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load(['member', 'gymClass.room', 'gymClass.coach']);

        return response()->json($attendance);
    }

    /**
     * Update the specified attendance record.
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'checked_in_at' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $this->attendances->update($attendance, $validated);

        return response()->json($attendance->load(['member', 'gymClass']));
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->attendances->delete($attendance);

        return response()->json(['message' => 'Attendance deleted successfully']);
    }
}
