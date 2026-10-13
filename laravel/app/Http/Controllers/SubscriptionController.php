<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of subscriptions.
     */
    public function index(): JsonResponse
    {
        $subscriptions = Subscription::with('member')->get();

        return response()->json($subscriptions);
    }

    /**
     * Store a newly created subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id'  => 'required|integer|exists:members,member_id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'price'      => 'nullable|numeric|min:0',
        ]);

        $subscription = Subscription::create($validated);

        return response()->json($subscription->load('member'), 201);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load('member');

        return response()->json($subscription);
    }

    /**
     * Update the specified subscription.
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $startDate = $request->input('start_date', $subscription->start_date?->format('Y-m-d'));

        $validated = $request->validate([
            'member_id'  => 'sometimes|required|integer|exists:members,member_id',
            'start_date' => 'sometimes|required|date',
            'end_date'   => 'sometimes|required|date|after_or_equal:' . $startDate,
            'price'      => 'nullable|numeric|min:0',
        ]);

        $subscription->update($validated);

        return response()->json($subscription->load('member'));
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted successfully']);
    }
}
