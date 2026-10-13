<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Display a listing of members.
     */
    public function index(): JsonResponse
    {
        $members = Member::withCount(['subscriptions', 'classes'])->get();

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
