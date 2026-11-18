<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Attendance;
use App\Service\AttendanceService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/attendances')]
final class AttendanceController extends AbstractController
{
    private const STATUSES = 'in:present,absent,late';

    public function __construct(private readonly AttendanceService $attendances)
    {
    }

    /**
     * Display a listing of attendance records.
     */
    #[Route('', name: 'api_attendances_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Attendance::class);

        $filters = $validator->validate($request, [
            'member_id' => 'nullable|integer|exists:members,member_id',
            'class_id' => 'nullable|integer|exists:classes,class_id',
            'status' => 'nullable|'.self::STATUSES,
            'checked_in_from' => 'nullable|date',
            'checked_in_to' => 'nullable|date|after_or_equal:checked_in_from',
            'sort' => 'nullable|in:attendance_id,status,checked_in_at',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->attendances->paginate($filters, $request));
    }

    /**
     * Record attendance for a member registered to a class.
     */
    #[Route('', name: 'api_attendances_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Attendance::class);

        $validated = $validator->validate($request, [
            'member_id' => 'required|integer|exists:members,member_id',
            'class_id' => 'required|integer|exists:classes,class_id',
            'status' => 'nullable|'.self::STATUSES,
            'checked_in_at' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $attendance = $this->attendances->create($validated);

        return $this->json($attendance->toArray(withMember: true, withClass: true), 201);
    }

    /**
     * Display the specified attendance record.
     */
    #[Route('/{attendance}', name: 'api_attendances_show', methods: ['GET'], requirements: ['attendance' => '\d+'])]
    public function show(#[MapEntity(id: 'attendance')] Attendance $attendance): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $attendance);

        return $this->json($attendance->toArray(withMember: true, withClass: true, withClassRelations: true));
    }

    /**
     * Update the specified attendance record.
     */
    #[Route('/{attendance}', name: 'api_attendances_update', methods: ['PUT', 'PATCH'], requirements: ['attendance' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'attendance')] Attendance $attendance, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $attendance);

        $validated = $validator->validate($request, [
            'status' => 'nullable|'.self::STATUSES,
            'checked_in_at' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $this->attendances->update($attendance, $validated);

        return $this->json($attendance->toArray(withMember: true, withClass: true));
    }

    /**
     * Remove the specified attendance record.
     */
    #[Route('/{attendance}', name: 'api_attendances_destroy', methods: ['DELETE'], requirements: ['attendance' => '\d+'])]
    public function destroy(#[MapEntity(id: 'attendance')] Attendance $attendance): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $attendance);

        $this->attendances->delete($attendance);

        return $this->json(['message' => 'Attendance deleted successfully']);
    }
}
