<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Member;
use App\Entity\Payment;
use App\Entity\Room;
use App\Entity\Subscription;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\Role;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewEntitiesTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->createUser(Role::Admin, 'admin@test.local', 'password');
        $this->token = $this->tokenFor($admin);
    }

    public function test_rooms_crud_with_filters_and_pagination(): void
    {
        [$status, $room] = $this->request('POST', '/api/rooms', [
            'name' => 'Зала А',
            'capacity' => 30,
            'description' => 'Основна зала',
        ], $this->token);
        self::assertSame(201, $status);
        $roomId = $room['room_id'];

        [$status, $list] = $this->request(
            'GET',
            '/api/rooms?name='.rawurlencode('Зала').'&capacity_min=20&per_page=5',
            null,
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);
        foreach (['data', 'current_page', 'per_page', 'total', 'last_page'] as $key) {
            self::assertArrayHasKey($key, $list);
        }

        [$status, $show] = $this->request('GET', '/api/rooms/'.$roomId, null, $this->token);
        self::assertSame(200, $status);
        self::assertSame('Зала А', $show['name']);

        [$status, $updated] = $this->request('PUT', '/api/rooms/'.$roomId, ['capacity' => 40], $this->token);
        self::assertSame(200, $status);
        self::assertSame(40, $updated['capacity']);

        [$status] = $this->request('DELETE', '/api/rooms/'.$roomId, null, $this->token);
        self::assertSame(200, $status);
        self::assertNull($this->entityManager->find(Room::class, $roomId));
    }

    public function test_equipment_crud_linked_to_room(): void
    {
        $room = new Room();
        $room->setName('Зала Б');
        $room->setCapacity(15);
        $this->persist($room);

        [$status, $equipment] = $this->request('POST', '/api/equipment', [
            'name' => 'Гантель',
            'quantity' => 10,
            'condition' => 'good',
            'room_id' => $room->getId(),
        ], $this->token);
        self::assertSame(201, $status);
        self::assertSame('Зала Б', $equipment['room']['name']);
        $equipmentId = $equipment['equipment_id'];

        [$status, $list] = $this->request(
            'GET',
            '/api/equipment?room_id='.$room->getId().'&condition=good&quantity_min=5',
            null,
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $updated] = $this->request(
            'PUT',
            '/api/equipment/'.$equipmentId,
            ['condition' => 'broken'],
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertSame('broken', $updated['condition']);

        [$status] = $this->request('DELETE', '/api/equipment/'.$equipmentId, null, $this->token);
        self::assertSame(200, $status);
        self::assertNull($this->entityManager->find(\App\Entity\Equipment::class, $equipmentId));
    }

    public function test_membership_plans_crud_with_filters(): void
    {
        [$status, $plan] = $this->request('POST', '/api/plans', [
            'name' => 'Місячний',
            'duration_days' => 30,
            'price' => 900,
            'is_active' => true,
        ], $this->token);
        self::assertSame(201, $status);
        $planId = $plan['plan_id'];

        [$status, $list] = $this->request(
            'GET',
            '/api/plans?is_active=1&price_min=500&duration_max=31',
            null,
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $updated] = $this->request('PUT', '/api/plans/'.$planId, ['price' => 1000], $this->token);
        self::assertSame(200, $status);
        self::assertSame('1000.00', $updated['price']);

        [$status] = $this->request('DELETE', '/api/plans/'.$planId, null, $this->token);
        self::assertSame(200, $status);
        self::assertNull($this->entityManager->find(\App\Entity\MembershipPlan::class, $planId));
    }

    public function test_payments_crud_with_filters(): void
    {
        [$member, $subscription] = $this->makeSubscription();

        [$status, $payment] = $this->request('POST', '/api/payments', [
            'sub_id' => $subscription->getId(),
            'amount' => 900,
            'payment_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'method' => 'card',
            'status' => 'pending',
        ], $this->token);
        self::assertSame(201, $status);
        $paymentId = $payment['payment_id'];

        [$status, $list] = $this->request(
            'GET',
            '/api/payments?sub_id='.$subscription->getId().'&status=pending&method=card',
            null,
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertCount(1, $list['data']);

        [$status, $show] = $this->request('GET', '/api/payments/'.$paymentId, null, $this->token);
        self::assertSame(200, $status);
        self::assertSame($member->getId(), $show['subscription']['member']['member_id']);

        [$status, $updated] = $this->request(
            'PUT',
            '/api/payments/'.$paymentId,
            ['status' => 'completed'],
            $this->token,
        );
        self::assertSame(200, $status);
        self::assertSame('completed', $updated['status']);

        [$status] = $this->request('DELETE', '/api/payments/'.$paymentId, null, $this->token);
        self::assertSame(200, $status);
        self::assertNull($this->entityManager->find(Payment::class, $paymentId));
    }

    public function test_cron_marks_past_pending_payments_as_overdue(): void
    {
        [, $subscription] = $this->makeSubscription();

        $overdue = new Payment();
        $overdue->setSubscription($subscription);
        $overdue->setAmount('500.00');
        $overdue->setPaymentDate(new \DateTimeImmutable('-3 days'));
        $overdue->setMethod(PaymentMethod::Cash);
        $overdue->setStatus(PaymentStatus::Pending);
        $this->persist($overdue);

        $future = new Payment();
        $future->setSubscription($subscription);
        $future->setAmount('500.00');
        $future->setPaymentDate(new \DateTimeImmutable('+3 days'));
        $future->setMethod(PaymentMethod::Cash);
        $future->setStatus(PaymentStatus::Pending);
        $this->persist($future);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('payments:mark-overdue'));
        $tester->execute([]);

        self::assertStringContainsString('Marked 1 payment(s) as overdue.', $tester->getDisplay());

        $this->entityManager->clear();
        self::assertSame(PaymentStatus::Overdue, $this->entityManager->find(Payment::class, $overdue->getId())->getStatus());
        self::assertSame(PaymentStatus::Pending, $this->entityManager->find(Payment::class, $future->getId())->getStatus());
    }

    public function test_pagination_and_sorting(): void
    {
        foreach (['Charlie', 'Alice', 'Bob'] as $name) {
            $member = new Member();
            $member->setName($name);
            $member->setJoinDate(new \DateTimeImmutable('today'));
            $this->persist($member);
        }

        [$status, $body] = $this->request(
            'GET',
            '/api/members?per_page=2&page=2&sort=name&direction=asc',
            null,
            $this->token,
        );

        self::assertSame(200, $status);
        self::assertSame(2, $body['current_page']);
        self::assertSame(2, $body['per_page']);
        self::assertSame(3, $body['total']);
        self::assertSame(2, $body['last_page']);
        self::assertCount(1, $body['data']);
        self::assertSame('Charlie', $body['data'][0]['name']);
    }

    /**
     * @return array{0: Member, 1: Subscription}
     */
    private function makeSubscription(): array
    {
        $member = new Member();
        $member->setName('Тестовий Клієнт');
        $member->setPhone('123456');
        $member->setJoinDate(new \DateTimeImmutable('today'));
        $this->persist($member);

        $subscription = new Subscription();
        $subscription->setMember($member);
        $subscription->setStartDate(new \DateTimeImmutable('-1 month'));
        $subscription->setEndDate(new \DateTimeImmutable('+1 month'));
        $subscription->setPrice('900.00');
        $this->persist($subscription);

        return [$member, $subscription];
    }
}
