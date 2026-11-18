<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthService;
use App\Validation\RequestValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /**
     * Register a new client account and its member profile.
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request, RequestValidator $validator): JsonResponse
    {
        $validated = $validator->validate($request, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $this->auth->register($validated);
        $token = $this->auth->login($user);

        return $this->tokenResponse($token, $user, 'Registered successfully.', 201);
    }

    /**
     * Authenticate with email + password and issue a JWT.
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, RequestValidator $validator): JsonResponse
    {
        $credentials = $validator->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->auth->attempt($credentials['email'], $credentials['password']);

        if (null === $result) {
            return $this->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->tokenResponse($result['token'], $result['user']);
    }

    /**
     * Return the currently authenticated user.
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json($this->currentUser()->toArray(withMember: true));
    }

    /**
     * Rotate the JWT (the old token is blacklisted).
     */
    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $token = $this->auth->refresh($request, $user);

        return $this->tokenResponse($token, $user, 'Token refreshed.');
    }

    /**
     * Invalidate the current JWT.
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return $this->json(['message' => 'Logged out successfully.']);
    }

    private function tokenResponse(string $token, User $user, string $message = 'Login successful.', int $status = 200): JsonResponse
    {
        return $this->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->auth->expiresIn(),
            'user' => $user->toArray(withMember: true),
        ], $status);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
