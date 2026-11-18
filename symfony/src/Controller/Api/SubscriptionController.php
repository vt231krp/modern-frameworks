<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Subscription;
use App\Service\SubscriptionService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/subscriptions')]
final class SubscriptionController extends AbstractController
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Display a listing of subscriptions.
     */
    #[Route('', name: 'api_subscriptions_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Subscription::class);

        $filters = $validator->validate($request, [
            'member_id' => 'nullable|integer|exists:members,member_id',
            'plan_id' => 'nullable|integer|exists:membership_plans,plan_id',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'start_date_from' => 'nullable|date',
            'start_date_to' => 'nullable|date|after_or_equal:start_date_from',
            'end_date_from' => 'nullable|date',
            'end_date_to' => 'nullable|date|after_or_equal:end_date_from',
            'active' => 'nullable|boolean',
            'sort' => 'nullable|in:sub_id,member_id,plan_id,start_date,end_date,price',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->subscriptions->paginate($filters, $request));
    }

    /**
     * Store a newly created subscription.
     */
    #[Route('', name: 'api_subscriptions_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Subscription::class);

        $validated = $validator->validate($request, [
            'member_id' => 'required|integer|exists:members,member_id',
            'plan_id' => 'nullable|integer|exists:membership_plans,plan_id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|required_without:plan_id|date|after_or_equal:start_date',
            'price' => 'nullable|numeric|min:0',
        ]);

        return $this->json($this->subscriptions->create($validated)->toArray(withMember: true, withPlan: true), 201);
    }

    /**
     * Display the specified subscription.
     */
    #[Route('/{subscription}', name: 'api_subscriptions_show', methods: ['GET'], requirements: ['subscription' => '\d+'])]
    public function show(#[MapEntity(id: 'subscription')] Subscription $subscription): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $subscription);

        return $this->json($subscription->toArray(withMember: true, withPlan: true, withPayments: true));
    }

    /**
     * Update the specified subscription.
     */
    #[Route('/{subscription}', name: 'api_subscriptions_update', methods: ['PUT', 'PATCH'], requirements: ['subscription' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'subscription')] Subscription $subscription, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $subscription);

        $body = json_decode($request->getContent(), true);
        $startDate = \is_array($body) && isset($body['start_date']) && \is_string($body['start_date'])
            ? $body['start_date']
            : $subscription->getStartDate()?->format('Y-m-d');

        $validated = $validator->validate($request, [
            'member_id' => 'sometimes|required|integer|exists:members,member_id',
            'plan_id' => 'nullable|integer|exists:membership_plans,plan_id',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:'.$startDate,
            'price' => 'nullable|numeric|min:0',
        ]);

        $this->subscriptions->update($subscription, $validated);

        return $this->json($subscription->toArray(withMember: true, withPlan: true));
    }

    /**
     * Remove the specified subscription.
     */
    #[Route('/{subscription}', name: 'api_subscriptions_destroy', methods: ['DELETE'], requirements: ['subscription' => '\d+'])]
    public function destroy(#[MapEntity(id: 'subscription')] Subscription $subscription): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $subscription);

        $this->subscriptions->delete($subscription);

        return $this->json(['message' => 'Subscription deleted successfully']);
    }
}
