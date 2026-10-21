<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

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

        $token = $this->guard()->login($user);

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

        $token = $this->guard()->attempt($credentials);

        if (! is_string($token)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->tokenResponse($token, $this->currentUser()->load('member'));
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(): JsonResponse
    {
        return response()->json($this->currentUser()->load('member'));
    }

    /**
     * Rotate the JWT (the old token is blacklisted).
     */
    public function refresh(): JsonResponse
    {
        $token = $this->guard()->refresh();

        return $this->tokenResponse($token, $this->currentUser()->load('member'), 'Token refreshed.');
    }

    /**
     * Invalidate the current JWT.
     */
    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function tokenResponse(string $token, User $user, string $message = 'Login successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) $this->guard()->getTTL() * 60,
            'user' => $user,
        ], $status);
    }

    /**
     * Resolve the JWT guard with a concrete type for static analysis.
     */
    private function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        assert($guard instanceof JWTGuard);

        return $guard;
    }

    /**
     * Resolve the authenticated user or abort with 401.
     */
    private function currentUser(): User
    {
        $user = $this->guard()->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }
}
