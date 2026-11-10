<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $members)
    {
        $this->authorizeResource(Member::class, 'member');
    }

    /**
     * Display a listing of members.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'join_date_from' => 'nullable|date',
            'join_date_to' => ['nullable', 'date', Rule::when(
                $request->filled('join_date_from'),
                'after_or_equal:join_date_from'
            )],
            'active' => 'nullable|boolean',
            'sort' => 'nullable|in:member_id,name,join_date',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->members->paginate($filters));
    }

    /**
     * Store a newly created member.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'join_date' => 'nullable|date',
        ]);

        $member = $this->members->create($validated);

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
            'name' => 'sometimes|required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'join_date' => 'sometimes|required|date',
        ]);

        $this->members->update($member, $validated);

        return response()->json($member);
    }

    /**
     * Remove the specified member.
     */
    public function destroy(Member $member): JsonResponse
    {
        $this->members->delete($member);

        return response()->json(['message' => 'Member deleted successfully']);
    }
}
