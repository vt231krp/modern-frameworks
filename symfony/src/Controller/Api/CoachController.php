<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Coach;
use App\Service\CoachService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/coaches')]
final class CoachController extends AbstractController
{
    public function __construct(private readonly CoachService $coaches)
    {
    }

    /**
     * Display a listing of coaches.
     */
    #[Route('', name: 'api_coaches_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Coach::class);

        $filters = $validator->validate($request, [
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'with_classes' => 'nullable|boolean',
            'sort' => 'nullable|in:coach_id,name',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->coaches->paginate($filters, $request));
    }

    /**
     * Store a newly created coach.
     */
    #[Route('', name: 'api_coaches_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Coach::class);

        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $coach = $this->coaches->create($validated);

        return $this->json($coach->toArray(), 201);
    }

    /**
     * Display the specified coach.
     */
    #[Route('/{coach}', name: 'api_coaches_show', methods: ['GET'], requirements: ['coach' => '\d+'])]
    public function show(#[MapEntity(id: 'coach')] Coach $coach): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $coach);

        return $this->json($coach->toArray(withClasses: true));
    }

    /**
     * Update the specified coach.
     */
    #[Route('/{coach}', name: 'api_coaches_update', methods: ['PUT', 'PATCH'], requirements: ['coach' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'coach')] Coach $coach, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $coach);

        $validated = $validator->validate($request, [
            'name' => 'sometimes|required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $this->coaches->update($coach, $validated);

        return $this->json($coach->toArray());
    }

    /**
     * Remove the specified coach.
     */
    #[Route('/{coach}', name: 'api_coaches_destroy', methods: ['DELETE'], requirements: ['coach' => '\d+'])]
    public function destroy(#[MapEntity(id: 'coach')] Coach $coach): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $coach);

        $this->coaches->delete($coach);

        return $this->json(['message' => 'Coach deleted successfully']);
    }
}
