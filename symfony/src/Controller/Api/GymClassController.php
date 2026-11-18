<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\GymClass;
use App\Service\GymClassService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/classes')]
final class GymClassController extends AbstractController
{
    public function __construct(private readonly GymClassService $classes)
    {
    }

    /**
     * Display a listing of classes.
     */
    #[Route('', name: 'api_classes_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', GymClass::class);

        $filters = $validator->validate($request, [
            'title' => 'nullable|string|max:100',
            'coach_id' => 'nullable|integer|exists:coaches,coach_id',
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'class_time_from' => 'nullable|date',
            'class_time_to' => 'nullable|date|after_or_equal:class_time_from',
            'capacity_min' => 'nullable|integer|min:0',
            'capacity_max' => 'nullable|integer|min:0',
            'available' => 'nullable|boolean',
            'sort' => 'nullable|in:class_id,title,class_time,capacity',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->classes->paginate($filters, $request));
    }

    /**
     * Store a newly created class.
     */
    #[Route('', name: 'api_classes_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', GymClass::class);

        $validated = $validator->validate($request, [
            'title' => 'required|string|max:100',
            'coach_id' => 'nullable|integer|exists:coaches,coach_id',
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'class_time' => 'required|date',
            'capacity' => 'nullable|integer|min:1',
        ]);

        return $this->json($this->classes->create($validated)->toArray(withCoach: true, withRoom: true), 201);
    }

    /**
     * Display the specified class.
     */
    #[Route('/{class}', name: 'api_classes_show', methods: ['GET'], requirements: ['class' => '\d+'])]
    public function show(#[MapEntity(id: 'class')] GymClass $class): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $class);

        return $this->json($class->toArray(withCoach: true, withRoom: true, withMembers: true));
    }

    /**
     * Update the specified class.
     */
    #[Route('/{class}', name: 'api_classes_update', methods: ['PUT', 'PATCH'], requirements: ['class' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'class')] GymClass $class, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $class);

        $validated = $validator->validate($request, [
            'title' => 'sometimes|required|string|max:100',
            'coach_id' => 'nullable|integer|exists:coaches,coach_id',
            'room_id' => 'nullable|integer|exists:rooms,room_id',
            'class_time' => 'sometimes|required|date',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $this->classes->update($class, $validated);

        return $this->json($class->toArray(withCoach: true, withRoom: true));
    }

    /**
     * Remove the specified class.
     */
    #[Route('/{class}', name: 'api_classes_destroy', methods: ['DELETE'], requirements: ['class' => '\d+'])]
    public function destroy(#[MapEntity(id: 'class')] GymClass $class): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $class);

        $this->classes->delete($class);

        return $this->json(['message' => 'Class deleted successfully']);
    }
}
