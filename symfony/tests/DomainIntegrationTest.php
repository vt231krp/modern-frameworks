<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Attendance;
use App\Entity\Coach;
use App\Entity\GymClass;
use App\Entity\Member;
use App\Entity\Registration;
use App\Entity\Room;
use App\Enum\Role;

class DomainIntegrationTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->createUser(Role::Admin, 'admin@test.local', 'password');
        $this->token = $this->tokenFor($admin);
    }

    public function test_subscription_derives_price_and_end_date_from_plan(): void
    {
        [$status, $plan] = $this->request('POST', '/api/plans', [
            'name' => 'Місячний',
            'duration_days' => 30,
            'price' => 900,
            'is_active' => true,
        ], $this->token);
        self::assertSame(201, $status);

        $member = new Member();
        $member->setName('Клієнт');
        $member->setPhone('123456');
        $member->setJoinDate(new \DateTimeImmutable('today'));
        $this->persist($member);

        [$status, $subscription] = $this->request('POST', '/api/subscriptions', [
            'member_id' => $member->getId(),
            'plan_id' => $plan['plan_id'],
            'start_date' => '2026-11-10',
        ], $this->token);

        self::assertSame(201, $status);
        self::assertSame($plan['plan_id'], $subscription['plan_id']);
        self::assertSame('Місячний', $subscription['plan']['name']);
        self::assertSame('900.00', $subscription['price']);
        self::assertSame('2026-12-10', $subscription['end_date']);

        [$status, $list] = $this->request('GET', '/api/subscriptions?plan_id='.$plan['plan_id'], null, $this->token);
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $plans] = $this->request('GET', '/api/plans', null, $this->token);
        self::assertSame(200, $status);
        self::assertSame(1, $plans['data'][0]['subscriptions_count']);

        [$status, $inactivePlan] = $this->request('POST', '/api/plans', [
            'name' => 'Архівний',
            'duration_days' => 30,
            'price' => 100,
            'is_active' => false,
        ], $this->token);
        self::assertSame(201, $status);

        [$status, $body] = $this->request('POST', '/api/subscriptions', [
            'member_id' => $member->getId(),
            'plan_id' => $inactivePlan['plan_id'],
            'start_date' => '2026-11-10',
        ], $this->token);

        self::assertSame(422, $status);
        self::assertArrayHasKey('plan_id', $body['errors']);
    }

    public function test_class_capacity_must_fit_the_room(): void
    {
        $room = new Room();
        $room->setName('Зала А');
        $room->setCapacity(10);
        $this->persist($room);

        [$status, $body] = $this->request('POST', '/api/classes', [
            'title' => 'Бокс',
            'room_id' => $room->getId(),
            'class_time' => '2026-12-01 18:00:00',
            'capacity' => 11,
        ], $this->token);
        self::assertSame(422, $status);
        self::assertArrayHasKey('capacity', $body['errors']);

        [$status, $class] = $this->request('POST', '/api/classes', [
            'title' => 'Бокс',
            'room_id' => $room->getId(),
            'class_time' => '2026-12-01 18:00:00',
            'capacity' => 10,
        ], $this->token);
        self::assertSame(201, $status);
        self::assertSame('Зала А', $class['room']['name']);

        [$status, $list] = $this->request('GET', '/api/classes?room_id='.$room->getId(), null, $this->token);
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $roomBody] = $this->request('GET', '/api/rooms/'.$room->getId(), null, $this->token);
        self::assertSame(200, $status);
        self::assertCount(1, $roomBody['classes']);
        self::assertSame($class['class_id'], $roomBody['classes'][0]['class_id']);

        [$status, $body] = $this->request('PUT', '/api/rooms/'.$room->getId(), ['capacity' => 5], $this->token);
        self::assertSame(422, $status);
        self::assertArrayHasKey('capacity', $body['errors']);
    }

    public function test_attendance_requires_a_registration_and_tracks_visits(): void
    {
        $room = new Room();
        $room->setName('Зала Б');
        $room->setCapacity(20);
        $this->persist($room);

        $coach = new Coach();
        $coach->setName('Тренер');
        $coach->setPhone('123');
        $this->persist($coach);

        $member = new Member();
        $member->setName('Відвідувач');
        $member->setPhone('456');
        $member->setJoinDate(new \DateTimeImmutable('today'));
        $this->persist($member);

        $stranger = new Member();
        $stranger->setName('Не записаний');
        $stranger->setPhone('789');
        $stranger->setJoinDate(new \DateTimeImmutable('today'));
        $this->persist($stranger);

        $class = new GymClass();
        $class->setTitle('Йога');
        $class->setCoach($coach);
        $class->setRoom($room);
        $class->setClassTime(new \DateTimeImmutable('2026-12-02 10:00:00'));
        $class->setCapacity(10);
        $this->persist($class);

        $registration = new Registration();
        $registration->setMember($member);
        $registration->setGymClass($class);
        $this->persist($registration);

        [$status, $body] = $this->request('POST', '/api/attendances', [
            'member_id' => $stranger->getId(),
            'class_id' => $class->getId(),
        ], $this->token);
        self::assertSame(422, $status);
        self::assertArrayHasKey('member_id', $body['errors']);

        [$status, $attendance] = $this->request('POST', '/api/attendances', [
            'member_id' => $member->getId(),
            'class_id' => $class->getId(),
            'status' => 'present',
        ], $this->token);
        self::assertSame(201, $status);
        self::assertSame($member->getId(), $attendance['member']['member_id']);
        self::assertSame($class->getId(), $attendance['gym_class']['class_id']);
        self::assertNotNull($attendance['checked_in_at']);

        [$status] = $this->request('POST', '/api/attendances', [
            'member_id' => $member->getId(),
            'class_id' => $class->getId(),
        ], $this->token);
        self::assertSame(422, $status);

        [$status, $list] = $this->request(
            'GET',
            '/api/attendances?member_id='.$member->getId().'&status=present',
            null,
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $updated] = $this->request(
            'PUT',
            '/api/attendances/'.$attendance['attendance_id'],
            ['status' => 'late', 'notes' => 'Запізнився на 10 хвилин'],
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertSame('late', $updated['status']);

        [$status] = $this->request('DELETE', '/api/attendances/'.$attendance['attendance_id'], null, $this->token);
        self::assertSame(200, $status);
        self::assertNull($this->entityManager->find(Attendance::class, $attendance['attendance_id']));
    }
}
