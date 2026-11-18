<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Coach;
use App\Entity\GymClass;
use App\Entity\Member;
use App\Enum\Role;

class GymApiTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->createUser(Role::Admin, 'admin@test.local', 'password');
        $this->token = $this->tokenFor($admin);
    }

    public function test_coach_crud(): void
    {
        [$status, $coach] = $this->request('POST', '/api/coaches', [
            'name' => 'Олександр Коваленко',
            'phone' => '+380991112233',
        ], $this->token);

        self::assertSame(201, $status);
        self::assertSame('Олександр Коваленко', $coach['name']);
        $coachId = $coach['coach_id'];

        [$status, $list] = $this->request('GET', '/api/coaches', null, $this->token);
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $show] = $this->request('GET', '/api/coaches/'.$coachId, null, $this->token);
        self::assertSame(200, $status);
        self::assertSame($coachId, $show['coach_id']);

        [$status, $updated] = $this->request('PUT', '/api/coaches/'.$coachId, [
            'name' => 'Олександр Оновлений',
        ], $this->token);
        self::assertSame(200, $status);
        self::assertSame('Олександр Оновлений', $updated['name']);

        [$status] = $this->request('DELETE', '/api/coaches/'.$coachId, null, $this->token);
        self::assertSame(200, $status);

        self::assertNull($this->entityManager->find(Coach::class, $coachId));
    }

    public function test_member_and_subscription_crud(): void
    {
        [$status, $member] = $this->request('POST', '/api/members', [
            'name' => 'Марія Іваненко',
            'phone' => '+380931234567',
        ], $this->token);
        self::assertSame(201, $status);
        $memberId = $member['member_id'];

        [$status, $subscription] = $this->request('POST', '/api/subscriptions', [
            'member_id' => $memberId,
            'start_date' => '2026-10-15',
            'end_date' => '2026-11-15',
            'price' => 1200.50,
        ], $this->token);
        self::assertSame(201, $status);
        $subId = $subscription['sub_id'];

        [$status] = $this->request('POST', '/api/subscriptions', [
            'member_id' => $memberId,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-01',
            'price' => 500,
        ], $this->token);
        self::assertSame(422, $status);

        [$status, $show] = $this->request('GET', '/api/subscriptions/'.$subId, null, $this->token);
        self::assertSame(200, $status);
        self::assertSame('Марія Іваненко', $show['member']['name']);

        [$status] = $this->request('DELETE', '/api/subscriptions/'.$subId, null, $this->token);
        self::assertSame(200, $status);
    }

    public function test_gym_class_and_registration(): void
    {
        $coach = new Coach();
        $coach->setName('Сергій');
        $coach->setPhone('12345');
        $this->persist($coach);

        $member = new Member();
        $member->setName('Іван');
        $member->setPhone('54321');
        $member->setJoinDate(new \DateTimeImmutable('today'));
        $this->persist($member);

        [$status, $class] = $this->request('POST', '/api/classes', [
            'title' => 'Бокс',
            'coach_id' => $coach->getId(),
            'class_time' => '2026-10-20 18:00:00',
            'capacity' => 1,
        ], $this->token);
        self::assertSame(201, $status);
        $classId = $class['class_id'];

        [$status] = $this->request('POST', '/api/registrations', [
            'member_id' => $member->getId(),
            'class_id' => $classId,
        ], $this->token);
        self::assertSame(201, $status);

        // Duplicate registration should fail.
        [$status] = $this->request('POST', '/api/registrations', [
            'member_id' => $member->getId(),
            'class_id' => $classId,
        ], $this->token);
        self::assertSame(422, $status);

        // A second member cannot register because capacity is one.
        $secondMember = new Member();
        $secondMember->setName('Андрій');
        $secondMember->setPhone('99999');
        $secondMember->setJoinDate(new \DateTimeImmutable('today'));
        $this->persist($secondMember);

        [$status] = $this->request('POST', '/api/registrations', [
            'member_id' => $secondMember->getId(),
            'class_id' => $classId,
        ], $this->token);
        self::assertSame(422, $status);

        [$status] = $this->request(
            'GET',
            sprintf('/api/registrations/%d/%d', $member->getId(), $classId),
            null,
            $this->token,
        );
        self::assertSame(200, $status);

        $secondClass = new GymClass();
        $secondClass->setTitle('Йога');
        $secondClass->setClassTime(new \DateTimeImmutable('2026-10-21 18:00:00'));
        $secondClass->setCapacity(5);
        $this->persist($secondClass);

        [$status, $updated] = $this->request(
            'PUT',
            sprintf('/api/registrations/%d/%d', $member->getId(), $classId),
            ['class_id' => $secondClass->getId()],
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertSame($secondClass->getId(), $updated['class_id']);

        self::assertNull($this->entityManager->getRepository(\App\Entity\Registration::class)->findOneBy([
            'member' => $member,
            'gymClass' => $this->entityManager->find(GymClass::class, $classId),
        ]));
        self::assertNotNull($this->entityManager->getRepository(\App\Entity\Registration::class)->findOneBy([
            'member' => $member,
            'gymClass' => $secondClass,
        ]));

        [$status] = $this->request(
            'DELETE',
            sprintf('/api/registrations/%d/%d', $member->getId(), $secondClass->getId()),
            null,
            $this->token,
        );
        self::assertSame(200, $status);

        self::assertNull($this->entityManager->getRepository(\App\Entity\Registration::class)->findOneBy([
            'member' => $member,
            'gymClass' => $secondClass,
        ]));
    }
}
