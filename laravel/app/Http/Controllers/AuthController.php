<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

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

        $user = $this->auth->register($validated);
        $token = $this->auth->login($user);

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

        $token = $this->auth->attempt($credentials);

        if ($token === null) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->tokenResponse($token, $this->auth->currentUser()->load('member'));
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(): JsonResponse
    {
        return response()->json($this->auth->currentUser()->load('member'));
    }

    /**
     * Rotate the JWT (the old token is blacklisted).
     */
    public function refresh(): JsonResponse
    {
        $token = $this->auth->refresh();

        return $this->tokenResponse($token, $this->auth->currentUser()->load('member'), 'Token refreshed.');
    }

    /**
     * Invalidate the current JWT.
     */
    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function tokenResponse(string $token, $user, string $message = 'Login successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->auth->expiresIn(),
            'user' => $user,
        ], $status);
    }
}
