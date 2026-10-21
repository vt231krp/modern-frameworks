<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
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

        $perPage = (int) ($filters['per_page'] ?? 15);

        $users = User::query()
            ->when(isset($filters['role']), fn ($q) => $q->where('role', $filters['role']))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            }))
            ->orderBy($filters['sort'] ?? 'id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);

        return response()->json($users);
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

        $user = DB::transaction(function () use ($validated) {
            $user = new User();
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = $validated['password'];
            $user->role = Role::from($validated['role']);
            $user->save();

            if ($user->role === Role::Client) {
                $member = new Member();
                $member->user_id = $user->id;
                $member->name = $user->name;
                $member->phone = $validated['phone'] ?? null;
                $member->join_date = now()->toDateString();
                $member->save();
            }

            return $user;
        });

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

        $user->update($validated);

        return response()->json($user->load('member'));
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $current */
        $current = $request->user();

        if ($user->id === $current->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

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

        $user->role = Role::from($validated['role']);
        $user->save();

        return response()->json($user->load('member'));
    }
}
