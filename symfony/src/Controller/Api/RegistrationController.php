<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Registration;
use App\Entity\User;
use App\Service\RegistrationService;
use App\Validation\RequestValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/registrations')]
final class RegistrationController extends AbstractController
{
    public function __construct(private readonly RegistrationService $registrations)
    {
    }

    /**
     * Display a listing of registrations.
     */
    #[Route('', name: 'api_registrations_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Registration::class);

        $filters = $validator->validate($request, [
            'member_id' => 'nullable|integer|exists:members,member_id',
            'class_id' => 'nullable|integer|exists:classes,class_id',
            'sort' => 'nullable|in:member_id,class_id',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->registrations->paginate($filters, $request));
    }

    /**
     * Register a member for a class.
     */
    #[Route('', name: 'api_registrations_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Registration::class);

        $validated = $validator->validate($request, [
            'member_id' => 'required|integer|exists:members,member_id',
            'class_id' => 'required|integer|exists:classes,class_id',
        ]);

        $registration = $this->registrations->create($validated, $this->currentUser());

        return $this->json($registration->toArray(withMember: true, withClass: true), 201);
    }

    /**
     * Display a specific registration by composite keys.
     */
    #[Route('/{memberId}/{classId}', name: 'api_registrations_show', methods: ['GET'], requirements: ['memberId' => '\d+', 'classId' => '\d+'])]
    public function show(int $memberId, int $classId): JsonResponse
    {
        $registration = $this->registrations->find($memberId, $classId);
        $this->denyAccessUnlessGranted('view', $registration);

        return $this->json($registration->toArray(withMember: true, withClass: true));
    }

    /**
     * Move a registration to another class.
     */
    #[Route('/{memberId}/{classId}', name: 'api_registrations_update', methods: ['PUT', 'PATCH'], requirements: ['memberId' => '\d+', 'classId' => '\d+'])]
    public function update(Request $request, int $memberId, int $classId, RequestValidator $validator): JsonResponse
    {
        $registration = $this->registrations->find($memberId, $classId);
        $this->denyAccessUnlessGranted('update', $registration);

        $validated = $validator->validate($request, [
            'class_id' => 'required|integer|exists:classes,class_id',
        ]);

        $updated = $this->registrations->update($memberId, $classId, (int) $validated['class_id'], $this->currentUser());

        return $this->json($updated->toArray(withMember: true, withClass: true));
    }

    /**
     * Remove the specified registration.
     */
    #[Route('/{memberId?}/{classId?}', name: 'api_registrations_destroy', methods: ['DELETE'], requirements: ['memberId' => '\d+', 'classId' => '\d+'])]
    public function destroy(Request $request, ?int $memberId = null, ?int $classId = null): JsonResponse
    {
        if (null === $memberId || null === $classId) {
            $body = json_decode($request->getContent(), true);
            $memberId ??= \is_array($body) ? ($body['member_id'] ?? null) : null;
            $classId ??= \is_array($body) ? ($body['class_id'] ?? null) : null;
        }

        if (null === $memberId || null === $classId) {
            return $this->json([
                'message' => 'Both member_id and class_id are required to delete a registration.',
            ], 422);
        }

        $registration = $this->registrations->find((int) $memberId, (int) $classId);
        $this->denyAccessUnlessGranted('delete', $registration);

        $this->registrations->delete((int) $memberId, (int) $classId);

        return $this->json(['message' => 'Registration cancelled successfully']);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
