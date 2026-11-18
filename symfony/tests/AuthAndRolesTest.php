<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Member;
use App\Enum\Role;

class AuthAndRolesTest extends ApiTestCase
{
    public function test_client_can_register_and_receives_jwt(): void
    {
        [$status, $body] = $this->request('POST', '/api/auth/register', [
            'name' => 'Новий Клієнт',
            'email' => 'client@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+380501112233',
        ]);

        self::assertSame(201, $status, json_encode($body, \JSON_UNESCAPED_UNICODE));
        self::assertSame('bearer', $body['token_type']);
        self::assertSame('client', $body['user']['role']);
        self::assertArrayHasKey('access_token', $body);
        self::assertArrayHasKey('expires_in', $body);
        self::assertArrayHasKey('member', $body['user']);
        self::assertNotEmpty($body['access_token']);

        $member = $this->entityManager
            ->getRepository(Member::class)
            ->findOneBy(['user' => $body['user']['id']]);

        self::assertInstanceOf(Member::class, $member);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createUser(Role::Client, 'client@example.test', 'password123');

        [$status] = $this->request('POST', '/api/auth/login', [
            'email' => 'client@example.test',
            'password' => 'wrong-password',
        ]);
        self::assertSame(401, $status);

        [$status, $body] = $this->request('POST', '/api/auth/login', [
            'email' => 'client@example.test',
            'password' => 'password123',
        ]);

        self::assertSame(200, $status);
        self::assertArrayHasKey('access_token', $body);
        self::assertSame('client@example.test', $body['user']['email']);
    }

    public function test_me_requires_valid_token(): void
    {
        [$status] = $this->request('GET', '/api/auth/me');
        self::assertSame(401, $status);

        $user = $this->createUser(Role::Client, 'me@example.test');
        [$status, $body] = $this->request('GET', '/api/auth/me', null, $this->tokenFor($user));

        self::assertSame(200, $status);
        self::assertSame('me@example.test', $body['email']);
    }

    public function test_client_cannot_create_staff_entities(): void
    {
        $client = $this->createUser(Role::Client, 'client@example.test');

        [$status] = $this->request(
            'POST',
            '/api/coaches',
            ['name' => 'Недоступний тренер'],
            $this->tokenFor($client),
        );

        self::assertSame(403, $status);
    }

    public function test_manager_can_create_but_not_delete(): void
    {
        $manager = $this->createUser(Role::Manager, 'manager@example.test');
        $token = $this->tokenFor($manager);

        [$status, $coach] = $this->request('POST', '/api/coaches', ['name' => 'Тренер Менеджера'], $token);
        self::assertSame(201, $status);

        [$status] = $this->request('DELETE', '/api/coaches/'.$coach['coach_id'], null, $token);
        self::assertSame(403, $status);
    }

    public function test_only_admin_can_manage_users(): void
    {
        $manager = $this->createUser(Role::Manager, 'manager@example.test');
        $admin = $this->createUser(Role::Admin, 'admin@example.test');

        [$status] = $this->request('GET', '/api/users', null, $this->tokenFor($manager));
        self::assertSame(403, $status);

        [$status, $body] = $this->request('GET', '/api/users', null, $this->tokenFor($admin));
        self::assertSame(200, $status);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('current_page', $body);
        self::assertArrayHasKey('per_page', $body);
        self::assertArrayHasKey('total', $body);
    }

    public function test_client_can_only_read_their_own_member_records(): void
    {
        $client = $this->createUser(Role::Client, 'owner@example.test');
        $other = $this->createUser(Role::Client, 'other@example.test');

        $member = new Member();
        $member->setUser($client);
        $member->setName('Owner');
        $member->setJoinDate(new \DateTimeImmutable('today'));
        $client->setMember($member);
        $this->persist($member);

        $otherMember = new Member();
        $otherMember->setUser($other);
        $otherMember->setName('Other');
        $otherMember->setJoinDate(new \DateTimeImmutable('today'));
        $other->setMember($otherMember);
        $this->persist($otherMember);

        $subscription = new \App\Entity\Subscription();
        $subscription->setMember($member);
        $subscription->setStartDate(new \DateTimeImmutable('today'));
        $subscription->setEndDate(new \DateTimeImmutable('+30 days'));
        $subscription->setPrice('900.00');
        $this->persist($subscription);

        $payment = new \App\Entity\Payment();
        $payment->setSubscription($subscription);
        $payment->setAmount('900.00');
        $payment->setPaymentDate(new \DateTimeImmutable('today'));
        $this->persist($payment);

        $token = $this->tokenFor($client);

        [$status] = $this->request('GET', '/api/members/'.$member->getId(), null, $token);
        self::assertSame(200, $status);

        [$status] = $this->request('GET', '/api/members/'.$otherMember->getId(), null, $token);
        self::assertSame(403, $status);

        [$status] = $this->request('GET', '/api/subscriptions/'.$subscription->getId(), null, $token);
        self::assertSame(200, $status);

        [$status] = $this->request('GET', '/api/payments/'.$payment->getId(), null, $token);
        self::assertSame(200, $status);

        [$status] = $this->request('GET', '/api/subscriptions', null, $token);
        self::assertSame(403, $status);

        [$status] = $this->request('GET', '/api/payments', null, $token);
        self::assertSame(403, $status);
    }

    public function test_refresh_and_logout(): void
    {        $user = $this->createUser(Role::Client, 'refresh@example.test');
        $token = $this->tokenFor($user);

        [$status, $body] = $this->request('POST', '/api/auth/refresh', null, $token);
        self::assertSame(200, $status);
        $refreshed = $body['access_token'];
        self::assertNotSame($token, $refreshed);

        // The old token is blacklisted by the refresh.
        [$status] = $this->request('GET', '/api/auth/me', null, $token);
        self::assertSame(401, $status);

        [$status] = $this->request('POST', '/api/auth/logout', null, $refreshed);
        self::assertSame(200, $status);

        [$status] = $this->request('GET', '/api/auth/me', null, $refreshed);
        self::assertSame(401, $status);
    }
}
