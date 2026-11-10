<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * Display a listing of users (admin only, enforced by route middleware).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(Role::values())],
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:id,name,email,role',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->users->paginate($filters));
    }

    /**
     * Create a user with an explicit role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in(Role::values())],
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $this->users->create($validated);

        return response()->json($user->load('member'), 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('member'));
    }

    /**
     * Update name, email or password. Role changes go through changeRole().
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', Password::min(8)],
        ]);

        $this->users->update($user, $validated);

        return response()->json($user->load('member'));
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $current */
        $current = $request->user();

        $this->users->delete($user, $current);

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Change a user's role.
     */
    public function changeRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(Role::values())],
        ]);

        /** @var User $current */
        $current = $request->user();

        if ($user->id === $current->id) {
            return response()->json(['message' => 'You cannot change your own role.'], 422);
        }

        $this->users->changeRole($user, $validated['role']);

        return response()->json($user->load('member'));
    }
}
