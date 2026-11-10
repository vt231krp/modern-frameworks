<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
        $this->authorizeResource(Subscription::class, 'subscription');
    }

    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'member_id' => 'nullable|integer|exists:members,member_id',
            'plan_id' => 'nullable|integer|exists:membership_plans,plan_id',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'start_date_from' => 'nullable|date',
            'start_date_to' => ['nullable', 'date', Rule::when(
                $request->filled('start_date_from'),
                'after_or_equal:start_date_from'
            )],
            'end_date_from' => 'nullable|date',
            'end_date_to' => ['nullable', 'date', Rule::when(
                $request->filled('end_date_from'),
                'after_or_equal:end_date_from'
            )],
            'active' => 'nullable|boolean',
            'sort' => 'nullable|in:sub_id,member_id,plan_id,start_date,end_date,price',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->subscriptions->paginate($filters));
    }

    /**
     * Store a newly created subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => 'required|integer|exists:members,member_id',
            'plan_id' => 'nullable|integer|exists:membership_plans,plan_id',
            'start_date' => 'required|date',
            'end_date' => ['nullable', 'required_without:plan_id', 'date', 'after_or_equal:start_date'],
            'price' => 'nullable|numeric|min:0',
        ]);

        $subscription = $this->subscriptions->create($validated);

        return response()->json($subscription->load(['member', 'plan']), 201);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load(['member', 'plan', 'payments']);

        return response()->json($subscription);
    }

    /**
     * Update the specified subscription.
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $startDate = $request->input('start_date', $subscription->start_date?->format('Y-m-d'));

        $validated = $request->validate([
            'member_id' => 'sometimes|required|integer|exists:members,member_id',
            'plan_id' => 'nullable|integer|exists:membership_plans,plan_id',
            'start_date' => 'sometimes|required|date',
            'end_date' => ['nullable', 'date', 'after_or_equal:'.$startDate],
            'price' => 'nullable|numeric|min:0',
        ]);

        $this->subscriptions->update($subscription, $validated);

        return response()->json($subscription->load(['member', 'plan']));
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->subscriptions->delete($subscription);

        return response()->json(['message' => 'Subscription deleted successfully']);
    }
}
