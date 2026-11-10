<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Services\MembershipPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipPlanController extends Controller
{
    public function __construct(private readonly MembershipPlanService $plans)
    {
        $this->authorizeResource(MembershipPlan::class, 'plan');
    }

    /**
     * Display a listing of membership plans.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
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

        return response()->json($this->plans->paginate($filters));
    }

    /**
     * Store a newly created membership plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'duration_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $plan = $this->plans->create($validated);

        return response()->json($plan, 201);
    }

    /**
     * Display the specified membership plan.
     */
    public function show(MembershipPlan $plan): JsonResponse
    {
        $plan->load(['subscriptions.member']);

        return response()->json($plan);
    }

    /**
     * Update the specified membership plan.
     */
    public function update(Request $request, MembershipPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'duration_days' => 'sometimes|required|integer|min:1',
            'price' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $this->plans->update($plan, $validated);

        return response()->json($plan);
    }

    /**
     * Remove the specified membership plan.
     */
    public function destroy(MembershipPlan $plan): JsonResponse
    {
        $this->plans->delete($plan);

        return response()->json(['message' => 'Membership plan deleted successfully']);
    }
}
