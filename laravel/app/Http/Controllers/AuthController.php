<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new client account and its member profile.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => 'nullable|string|max:20',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = new User();
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = $validated['password'];
            // Role is never taken from the request: self-registration is always a client.
            $user->role = Role::Client;
            $user->save();

            $member = new Member();
            $member->user_id = $user->id;
            $member->name = $user->name;
            $member->phone = $validated['phone'] ?? null;
            $member->join_date = now()->toDateString();
            $member->save();

            return $user;
        });

        $token = auth('api')->login($user);

        return $this->tokenResponse($token, $user->load('member'), 'Registered successfully.', 201);
    }

    /**
     * Authenticate with email + password and issue a JWT.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $token = auth('api')->attempt($credentials);

        if (! $token) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->tokenResponse($token, auth('api')->user()->load('member'));
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('member'));
    }

    /**
     * Rotate the JWT (the old token is blacklisted).
     */
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return $this->tokenResponse($token, auth('api')->user()->load('member'), 'Token refreshed.');
    }

    /**
     * Invalidate the current JWT.
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function tokenResponse(string $token, User $user, string $message = 'Login successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user,
        ], $status);
    }
}
