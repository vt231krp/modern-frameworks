<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use App\Entity\User;
use App\Enum\Role;
use App\Security\RevokedTokenStore;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RevokedTokenStore $revokedTokens,
        private readonly int $tokenTtl,
    ) {
    }

    /**
     * Create a client account together with its member profile.
     */
    public function register(array $data): User
    {
        return $this->entityManager->wrapInTransaction(function () use ($data): User {
            $user = new User();
            $user->setName($data['name']);
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            // Role is never taken from the request: self-registration is always a client.
            $user->setRole(Role::Client);
            $this->entityManager->persist($user);

            $member = new Member();
            $member->setUser($user);
            $member->setName($user->getName());
            $member->setPhone($data['phone'] ?? null);
            $member->setJoinDate(new \DateTimeImmutable('today'));
            $user->setMember($member);

            $this->entityManager->persist($member);

            return $user;
        });
    }

    /**
     * Authenticate the given credentials and return the user and a JWT,
     * or null on failure.
     *
     * @return array{user: User, token: string}|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        return ['user' => $user, 'token' => $this->login($user)];
    }

    /**
     * Issue a JWT for an already resolved user.
     */
    public function login(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Rotate the JWT; the previous token is blacklisted.
     */
    public function refresh(Request $request, User $user): string
    {
        $this->revokeCurrentToken($request);

        return $this->login($user);
    }

    /**
     * Invalidate the current JWT.
     */
    public function logout(Request $request): void
    {
        $this->revokeCurrentToken($request);
    }

    /**
     * JWT lifetime in seconds.
     */
    public function expiresIn(): int
    {
        return $this->tokenTtl;
    }

    private function revokeCurrentToken(Request $request): void
    {
        $token = $this->rawToken($request);

        if (null === $token) {
            return;
        }

        $payload = $this->jwtManager->parse($token);
        $jti = $payload['jti'] ?? null;

        if (!\is_string($jti)) {
            return;
        }

        $expiresAt = isset($payload['exp'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $payload['exp'])
            : new \DateTimeImmutable(sprintf('+%d seconds', $this->tokenTtl));

        $this->revokedTokens->revoke($jti, $expiresAt);
    }

    private function rawToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');

        if (null === $header || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
