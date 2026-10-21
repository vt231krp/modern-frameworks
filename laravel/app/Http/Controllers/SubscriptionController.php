<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Subscription::class, 'subscription');
    }

    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'member_id'       => 'nullable|integer|exists:members,member_id',
            'price_min'       => 'nullable|numeric|min:0',
            'price_max'       => 'nullable|numeric|min:0',
            'start_date_from' => 'nullable|date',
            'start_date_to'   => ['nullable', 'date', Rule::when(
                $request->filled('start_date_from'),
                'after_or_equal:start_date_from'
            )],
            'end_date_from'   => 'nullable|date',
            'end_date_to'     => ['nullable', 'date', Rule::when(
                $request->filled('end_date_from'),
                'after_or_equal:end_date_from'
            )],
            'active'          => 'nullable|boolean',
            'sort'            => 'nullable|in:sub_id,member_id,start_date,end_date,price',
            'direction'       => 'nullable|in:asc,desc',
            'per_page'        => 'nullable|integer|min:1|max:100',
        ]);

        $active = $request->filled('active') ? $request->boolean('active') : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $subscriptions = Subscription::with('member')
            ->when(isset($filters['member_id']), fn ($q) => $q->where('member_id', $filters['member_id']))
            ->when(isset($filters['price_min']), fn ($q) => $q->where('price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn ($q) => $q->where('price', '<=', $filters['price_max']))
            ->when(isset($filters['start_date_from']), fn ($q) => $q->whereDate('start_date', '>=', $filters['start_date_from']))
            ->when(isset($filters['start_date_to']), fn ($q) => $q->whereDate('start_date', '<=', $filters['start_date_to']))
            ->when(isset($filters['end_date_from']), fn ($q) => $q->whereDate('end_date', '>=', $filters['end_date_from']))
            ->when(isset($filters['end_date_to']), fn ($q) => $q->whereDate('end_date', '<=', $filters['end_date_to']))
            ->when($active === true, fn ($q) => $q->whereDate('end_date', '>=', now()->toDateString()))
            ->when($active === false, fn ($q) => $q->whereDate('end_date', '<', now()->toDateString()))
            ->orderBy($filters['sort'] ?? 'sub_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);

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
