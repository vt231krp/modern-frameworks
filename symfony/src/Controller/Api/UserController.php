<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Enum\Role;
use App\Service\UserService;
use App\Validation\RequestValidator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(private readonly UserService $users)
    {
    }

    /**
     * Display a listing of users (admin only).
     */
    #[Route('', name: 'api_users_index', methods: ['GET'])]
    public function index(Request $request, RequestValidator $validator): JsonResponse
    {
        $filters = $validator->validate($request, [
            'role' => 'nullable|in:'.implode(',', Role::values()),
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:id,name,email,role',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->json($this->users->paginate($filters, $request));
    }

    /**
     * Create a user with an explicit role.
     */
    #[Route('', name: 'api_users_store', methods: ['POST'])]
    public function store(Request $request, RequestValidator $validator): JsonResponse
    {
        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:'.implode(',', Role::values()),
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $this->users->create($validated);

        return $this->json($user->toArray(withMember: true), 201);
    }

    /**
     * Display the specified user.
     */
    #[Route('/{user}', name: 'api_users_show', methods: ['GET'], requirements: ['user' => '\d+'])]
    public function show(#[MapEntity(id: 'user')] User $user): JsonResponse
    {
        return $this->json($user->toArray(withMember: true));
    }

    /**
     * Update name, email or password. Role changes go through changeRole().
     */
    #[Route('/{user}', name: 'api_users_update', methods: ['PUT', 'PATCH'], requirements: ['user' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'user')] User $user, RequestValidator $validator): JsonResponse
    {
        $validated = $validator->validate($request, [
            'name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$user->getId().',id',
            'password' => 'sometimes|required|string|min:8',
        ]);

        $this->users->update($user, $validated);

        return $this->json($user->toArray(withMember: true));
    }

    /**
     * Remove the specified user.
     */
    #[Route('/{user}', name: 'api_users_destroy', methods: ['DELETE'], requirements: ['user' => '\d+'])]
    public function destroy(#[MapEntity(id: 'user')] User $user): JsonResponse
    {
        $current = $this->getUser();
        \assert($current instanceof User);

        $this->users->delete($user, $current);

        return $this->json(['message' => 'User deleted successfully']);
    }

    /**
     * Change a user's role.
     */
    #[Route('/{user}/role', name: 'api_users_change_role', methods: ['PATCH'], requirements: ['user' => '\d+'])]
    public function changeRole(Request $request, #[MapEntity(id: 'user')] User $user, RequestValidator $validator): JsonResponse
    {
        $validated = $validator->validate($request, [
            'role' => 'required|in:'.implode(',', Role::values()),
        ]);

        $current = $this->getUser();
        \assert($current instanceof User);

        if ($user->getId() === $current->getId()) {
            return $this->json(['message' => 'You cannot change your own role.'], 422);
        }

        $this->users->changeRole($user, $validated['role']);

        return $this->json($user->toArray(withMember: true));
    }
}
