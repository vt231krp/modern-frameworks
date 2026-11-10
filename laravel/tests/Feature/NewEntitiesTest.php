<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class NewEntitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);
        $admin->role = Role::Admin;
        $admin->save();

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($admin));
    }

    public function test_rooms_crud_with_filters_and_pagination(): void
    {
        $create = $this->postJson('/api/rooms', [
            'name' => 'Зала А',
            'capacity' => 30,
            'description' => 'Основна зала',
        ])->assertStatus(201);

        $roomId = $create->json('room_id');

        $this->getJson('/api/rooms?name=Зала&capacity_min=20&per_page=5')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page']);

        $this->getJson("/api/rooms/{$roomId}")
            ->assertStatus(200)
            ->assertJsonPath('name', 'Зала А');

        $this->putJson("/api/rooms/{$roomId}", ['capacity' => 40])
            ->assertStatus(200)
            ->assertJsonPath('capacity', 40);

        $this->deleteJson("/api/rooms/{$roomId}")->assertStatus(200);
        $this->assertDatabaseMissing('rooms', ['room_id' => $roomId]);
    }

    public function test_equipment_crud_linked_to_room(): void
    {
        $room = Room::create(['name' => 'Зала Б', 'capacity' => 15]);

        $create = $this->postJson('/api/equipment', [
            'name' => 'Гантель',
            'quantity' => 10,
            'condition' => 'good',
            'room_id' => $room->room_id,
        ])->assertStatus(201)
            ->assertJsonPath('room.name', 'Зала Б');

        $equipmentId = $create->json('equipment_id');

        $this->getJson("/api/equipment?room_id={$room->room_id}&condition=good&quantity_min=5")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/equipment/{$equipmentId}", ['condition' => 'broken'])
            ->assertStatus(200)
            ->assertJsonPath('condition', 'broken');

        $this->deleteJson("/api/equipment/{$equipmentId}")->assertStatus(200);
        $this->assertDatabaseMissing('equipment', ['equipment_id' => $equipmentId]);
    }

    public function test_membership_plans_crud_with_filters(): void
    {
        $create = $this->postJson('/api/plans', [
            'name' => 'Місячний',
            'duration_days' => 30,
            'price' => 900,
            'is_active' => true,
        ])->assertStatus(201);

        $planId = $create->json('plan_id');

        $this->getJson('/api/plans?is_active=1&price_min=500&duration_max=31')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/plans/{$planId}", ['price' => 1000])
            ->assertStatus(200)
            ->assertJsonPath('price', '1000.00');

        $this->deleteJson("/api/plans/{$planId}")->assertStatus(200);
        $this->assertDatabaseMissing('membership_plans', ['plan_id' => $planId]);
    }

    public function test_payments_crud_with_filters(): void
    {
        [$member, $subscription] = $this->makeSubscription();

        $create = $this->postJson('/api/payments', [
            'sub_id' => $subscription->sub_id,
            'amount' => 900,
            'payment_date' => now()->toDateString(),
            'method' => 'card',
            'status' => 'pending',
        ])->assertStatus(201);

        $paymentId = $create->json('payment_id');

        $this->getJson("/api/payments?sub_id={$subscription->sub_id}&status=pending&method=card")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/payments/{$paymentId}")
            ->assertStatus(200)
            ->assertJsonPath('subscription.member.member_id', $member->member_id);

        $this->putJson("/api/payments/{$paymentId}", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'completed');

        $this->deleteJson("/api/payments/{$paymentId}")->assertStatus(200);
        $this->assertDatabaseMissing('payments', ['payment_id' => $paymentId]);
    }

    public function test_cron_marks_past_pending_payments_as_overdue(): void
    {
        [, $subscription] = $this->makeSubscription();

        $overdue = Payment::create([
            'sub_id' => $subscription->sub_id,
            'amount' => 500,
            'payment_date' => now()->subDays(3)->toDateString(),
            'method' => 'cash',
            'status' => 'pending',
        ]);

        $future = Payment::create([
            'sub_id' => $subscription->sub_id,
            'amount' => 500,
            'payment_date' => now()->addDays(3)->toDateString(),
            'method' => 'cash',
            'status' => 'pending',
        ]);

        $this->artisan('payments:mark-overdue')
            ->expectsOutputToContain('Marked 1 payment(s) as overdue.')
            ->assertSuccessful();

        $this->assertSame('overdue', $overdue->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
    }

    /**
     * @return array{0: Member, 1: Subscription}
     */
    private function makeSubscription(): array
    {
        $member = Member::create(['name' => 'Тестовий Клієнт', 'phone' => '123456']);

        $subscription = Subscription::create([
            'member_id' => $member->member_id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'price' => 900,
        ]);

        return [$member, $subscription];
    }
}
