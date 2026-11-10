<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Coach;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\Registration;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class DomainIntegrationTest extends TestCase
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

    public function test_subscription_derives_price_and_end_date_from_plan(): void
    {
        $plan = $this->postJson('/api/plans', [
            'name' => 'Місячний',
            'duration_days' => 30,
            'price' => 900,
            'is_active' => true,
        ])->assertStatus(201)->json();

        $member = Member::create(['name' => 'Клієнт', 'phone' => '123456']);

        $subscription = $this->postJson('/api/subscriptions', [
            'member_id' => $member->member_id,
            'plan_id' => $plan['plan_id'],
            'start_date' => '2026-11-10',
        ])->assertStatus(201)
            ->assertJsonPath('plan_id', $plan['plan_id'])
            ->assertJsonPath('plan.name', 'Місячний')
            ->assertJsonPath('price', '900.00')
            ->json();

        $this->assertSame('2026-12-10', Carbon::parse($subscription['end_date'])->toDateString());

        // The plan now has a real subscriber and can be filtered by it.
        $this->getJson('/api/subscriptions?plan_id='.$plan['plan_id'])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/plans')
            ->assertStatus(200)
            ->assertJsonPath('data.0.subscriptions_count', 1);

        // An inactive plan cannot be used for a new subscription.
        $inactivePlan = $this->postJson('/api/plans', [
            'name' => 'Архівний',
            'duration_days' => 30,
            'price' => 100,
            'is_active' => false,
        ])->assertStatus(201)->json();

        $this->postJson('/api/subscriptions', [
            'member_id' => $member->member_id,
            'plan_id' => $inactivePlan['plan_id'],
            'start_date' => '2026-11-10',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_class_capacity_must_fit_the_room(): void
    {
        $room = Room::create(['name' => 'Зала А', 'capacity' => 10]);

        $this->postJson('/api/classes', [
            'title' => 'Бокс',
            'room_id' => $room->room_id,
            'class_time' => '2026-12-01 18:00:00',
            'capacity' => 11,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('capacity');

        $class = $this->postJson('/api/classes', [
            'title' => 'Бокс',
            'room_id' => $room->room_id,
            'class_time' => '2026-12-01 18:00:00',
            'capacity' => 10,
        ])->assertStatus(201)
            ->assertJsonPath('room.name', 'Зала А')
            ->json();

        $this->getJson('/api/classes?room_id='.$room->room_id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Room usage is visible through its classes.
        $this->getJson('/api/rooms/'.$room->room_id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'classes')
            ->assertJsonPath('classes.0.class_id', $class['class_id']);

        // The room cannot be shrunk below an already scheduled class.
        $this->putJson('/api/rooms/'.$room->room_id, ['capacity' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('capacity');
    }

    public function test_attendance_requires_a_registration_and_tracks_visits(): void
    {
        $room = Room::create(['name' => 'Зала Б', 'capacity' => 20]);
        $coach = Coach::create(['name' => 'Тренер', 'phone' => '123']);
        $member = Member::create(['name' => 'Відвідувач', 'phone' => '456']);
        $stranger = Member::create(['name' => 'Не записаний', 'phone' => '789']);

        $class = GymClass::create([
            'title' => 'Йога',
            'coach_id' => $coach->coach_id,
            'room_id' => $room->room_id,
            'class_time' => '2026-12-02 10:00:00',
            'capacity' => 10,
        ]);

        Registration::create([
            'member_id' => $member->member_id,
            'class_id' => $class->class_id,
        ]);

        // A member who is not registered cannot be marked as attended.
        $this->postJson('/api/attendances', [
            'member_id' => $stranger->member_id,
            'class_id' => $class->class_id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('member_id');

        $attendance = $this->postJson('/api/attendances', [
            'member_id' => $member->member_id,
            'class_id' => $class->class_id,
            'status' => 'present',
        ])->assertStatus(201)
            ->assertJsonPath('member.member_id', $member->member_id)
            ->assertJsonPath('gym_class.class_id', $class->class_id)
            ->json();

        $this->assertNotNull($attendance['checked_in_at']);

        // Duplicate attendance is rejected.
        $this->postJson('/api/attendances', [
            'member_id' => $member->member_id,
            'class_id' => $class->class_id,
        ])->assertStatus(422);

        $this->getJson('/api/attendances?member_id='.$member->member_id.'&status=present')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->putJson('/api/attendances/'.$attendance['attendance_id'], [
            'status' => 'late',
            'notes' => 'Запізнився на 10 хвилин',
        ])->assertStatus(200)
            ->assertJsonPath('status', 'late');

        $this->deleteJson('/api/attendances/'.$attendance['attendance_id'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('attendances', [
            'attendance_id' => $attendance['attendance_id'],
        ]);
    }
}
