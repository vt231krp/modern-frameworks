<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\MembershipPlan;
use App\Service\MembershipPlanService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/plans')]
final class MembershipPlanController extends AbstractController
{
    public function __construct(private readonly MembershipPlanService $plans)
    {
    }

    /**
     * Display a listing of membership plans.
     */
    #[Route('', name: 'api_plans_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', MembershipPlan::class);

        $filters = $validator->validate($request, [
            'name' => 'nullable|string|max:100',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'duration_min' => 'nullable|integer|min:1',
            'duration_max' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'sort' => 'nullable|in:plan_id,name,duration_days,price,is_active',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->plans->paginate($filters, $request));
    }

    /**
     * Store a newly created membership plan.
     */
    #[Route('', name: 'api_plans_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', MembershipPlan::class);

        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->json($this->plans->create($validated)->toArray(), 201);
    }

    /**
     * Display the specified membership plan.
     */
    #[Route('/{plan}', name: 'api_plans_show', methods: ['GET'], requirements: ['plan' => '\d+'])]
    public function show(#[MapEntity(id: 'plan')] MembershipPlan $plan): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $plan);

        return $this->json($plan->toArray(withSubscriptions: true));
    }

    /**
     * Update the specified membership plan.
     */
    #[Route('/{plan}', name: 'api_plans_update', methods: ['PUT', 'PATCH'], requirements: ['plan' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'plan')] MembershipPlan $plan, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $plan);

        $validated = $validator->validate($request, [
            'name' => 'sometimes|required|string|max:100',
            'duration_days' => 'sometimes|required|integer|min:1',
            'price' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $this->plans->update($plan, $validated);

        return $this->json($plan->toArray());
    }

    /**
     * Remove the specified membership plan.
     */
    #[Route('/{plan}', name: 'api_plans_destroy', methods: ['DELETE'], requirements: ['plan' => '\d+'])]
    public function destroy(#[MapEntity(id: 'plan')] MembershipPlan $plan): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $plan);

        $this->plans->delete($plan);

        return $this->json(['message' => 'Membership plan deleted successfully']);
    }
}
