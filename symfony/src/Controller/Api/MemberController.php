<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Member;
use App\Service\MemberService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/members')]
final class MemberController extends AbstractController
{
    public function __construct(private readonly MemberService $members)
    {
    }

    /**
     * Display a listing of members.
     */
    #[Route('', name: 'api_members_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('viewAny', Member::class);

        $filters = $validator->validate($request, [
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'join_date_from' => 'nullable|date',
            'join_date_to' => 'nullable|date|after_or_equal:join_date_from',
            'active' => 'nullable|boolean',
            'sort' => 'nullable|in:member_id,name,join_date',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->members->paginate($filters, $request));
    }

    /**
     * Store a newly created member.
     */
    #[Route('', name: 'api_members_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('create', Member::class);

        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'join_date' => 'nullable|date',
        ]);

        return $this->json($this->members->create($validated)->toArray(), 201);
    }

    /**
     * Display the specified member.
     */
    #[Route('/{member}', name: 'api_members_show', methods: ['GET'], requirements: ['member' => '\d+'])]
    public function show(#[MapEntity(id: 'member')] Member $member): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $member);

        return $this->json($member->toArray(withSubscriptions: true, withClasses: true));
    }

    /**
     * Update the specified member.
     */
    #[Route('/{member}', name: 'api_members_update', methods: ['PUT', 'PATCH'], requirements: ['member' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'member')] Member $member, RequestValidator $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('update', $member);

        $validated = $validator->validate($request, [
            'name' => 'sometimes|required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'join_date' => 'sometimes|required|date',
        ]);

        $this->members->update($member, $validated);

        return $this->json($member->toArray());
    }

    /**
     * Remove the specified member.
     */
    #[Route('/{member}', name: 'api_members_destroy', methods: ['DELETE'], requirements: ['member' => '\d+'])]
    public function destroy(#[MapEntity(id: 'member')] Member $member): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $member);

        $this->members->delete($member);

        return $this->json(['message' => 'Member deleted successfully']);
    }
}
