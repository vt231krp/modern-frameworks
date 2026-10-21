<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Member::class, 'member');
    }

    /**
     * Display a listing of members.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name'           => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'join_date_from' => 'nullable|date',
            'join_date_to'   => ['nullable', 'date', Rule::when(
                $request->filled('join_date_from'),
                'after_or_equal:join_date_from'
            )],
            'active'         => 'nullable|boolean',
            'sort'           => 'nullable|in:member_id,name,join_date',
            'direction'      => 'nullable|in:asc,desc',
            'per_page'       => 'nullable|integer|min:1|max:100',
        ]);

        $active = $request->filled('active') ? $request->boolean('active') : null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $members = Member::withCount(['subscriptions', 'classes'])
            ->when(isset($filters['name']), fn ($q) => $q->where('name', 'like', "%{$filters['name']}%"))
            ->when(isset($filters['phone']), fn ($q) => $q->where('phone', 'like', "%{$filters['phone']}%"))
            ->when(isset($filters['join_date_from']), fn ($q) => $q->whereDate('join_date', '>=', $filters['join_date_from']))
            ->when(isset($filters['join_date_to']), fn ($q) => $q->whereDate('join_date', '<=', $filters['join_date_to']))
            ->when($active === true, fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->where('end_date', '>=', now()->toDateString())))
            ->when($active === false, fn ($q) => $q->whereDoesntHave('subscriptions', fn ($s) => $s->where('end_date', '>=', now()->toDateString())))
            ->orderBy($filters['sort'] ?? 'member_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);

        return response()->json($members);
    }

    /**
     * Store a newly created member.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'join_date' => 'nullable|date',
        ]);

        $data = array_filter($validated, fn($value) => !is_null($value));
        $member = Member::create($data);

        return response()->json($member, 201);
    }

    /**
     * Display the specified member.
     */
    public function show(Member $member): JsonResponse
    {
        $member->load(['subscriptions', 'classes']);

        return response()->json($member);
    }

    /**
     * Update the specified member.
     */
    public function update(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'join_date' => 'sometimes|required|date',
        ]);

        $member->update($validated);

        return response()->json($member);
    }

    /**
     * Remove the specified member.
     */
    public function destroy(Member $member): JsonResponse
    {
        $member->delete();

        return response()->json(['message' => 'Member deleted successfully']);
    }
}
