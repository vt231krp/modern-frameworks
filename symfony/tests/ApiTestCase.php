<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();

        $databasePath = \dirname(__DIR__).'/var/data_test.db';
        if (is_file($databasePath)) {
            @unlink($databasePath);
        }

        $this->client = static::createClient();
        // Keep the same kernel/entity manager for the whole test so the file
        // database and the identity map survive multiple HTTP requests.
        $this->client->disableReboot();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    protected function createUser(Role $role, string $email, string $password = 'password123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setName(ucfirst($role->value));
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRole($role);

        $this->persist($user);

        return $user;
    }

    protected function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    protected function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return array{0: int, 1: array<string, mixed>|null}
     */
    protected function request(string $method, string $uri, ?array $data = null, ?string $token = null): array
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $headers,
            null === $data ? '' : (string) json_encode($data),
        );

        $response = $this->client->getResponse();
        $decoded = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), \is_array($decoded) ? $decoded : null];
    }
}
