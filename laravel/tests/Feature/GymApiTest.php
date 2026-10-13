<?php

namespace Tests\Feature;

use App\Models\Coach;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\Registration;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_crud(): void
    {
        // 1. Create
        $response = $this->postJson('/api/coaches', [
            'name'  => 'Олександр Коваленко',
            'phone' => '+380991112233',
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('name', 'Олександр Коваленко');

        $coachId = $response->json('coach_id');

        // 2. Index
        $this->getJson('/api/coaches')
            ->assertStatus(200)
            ->assertJsonCount(1);

        // 3. Show
        $this->getJson("/api/coaches/{$coachId}")
            ->assertStatus(200)
            ->assertJsonPath('coach_id', $coachId);

        // 4. Update
        $this->putJson("/api/coaches/{$coachId}", [
            'name' => 'Олександр Оновлений',
        ])->assertStatus(200)
            ->assertJsonPath('name', 'Олександр Оновлений');

        // 5. Delete
        $this->deleteJson("/api/coaches/{$coachId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('coaches', ['coach_id' => $coachId]);
    }

    public function test_member_and_subscription_crud(): void
    {
        // Create Member
        $memberRes = $this->postJson('/api/members', [
            'name'  => 'Марія Іваненко',
            'phone' => '+380931234567',
        ]);
        $memberRes->assertStatus(201);
        $memberId = $memberRes->json('member_id');

        // Create Valid Subscription
        $subRes = $this->postJson('/api/subscriptions', [
            'member_id'  => $memberId,
            'start_date' => '2026-10-15',
            'end_date'   => '2026-11-15',
            'price'      => 1200.50,
        ]);
        $subRes->assertStatus(201);
        $subId = $subRes->json('sub_id');

        // Test Subscription validation (end_date before start_date should fail)
        $this->postJson('/api/subscriptions', [
            'member_id'  => $memberId,
            'start_date' => '2026-10-15',
            'end_date'   => '2026-10-01',
            'price'      => 500,
        ])->assertStatus(422);

        // Show Subscription
        $this->getJson("/api/subscriptions/{$subId}")
            ->assertStatus(200)
            ->assertJsonPath('member.name', 'Марія Іваненко');

        // Delete Subscription
        $this->deleteJson("/api/subscriptions/{$subId}")
            ->assertStatus(200);
    }

    public function test_gym_class_and_registration(): void
    {
        $coach = Coach::create(['name' => 'Сергій', 'phone' => '12345']);
        $member = Member::create(['name' => 'Іван', 'phone' => '54321']);

        // Create Class
        $classRes = $this->postJson('/api/classes', [
            'title'      => 'Бокс',
            'coach_id'   => $coach->coach_id,
            'class_time' => '2026-10-20 18:00:00',
            'capacity'   => 1,
        ]);
        $classRes->assertStatus(201);
        $classId = $classRes->json('class_id');

        // Register Member to Class
        $regRes = $this->postJson('/api/registrations', [
            'member_id' => $member->member_id,
            'class_id'  => $classId,
        ]);
        $regRes->assertStatus(201);

        // Duplicate registration should fail
        $this->postJson('/api/registrations', [
            'member_id' => $member->member_id,
            'class_id'  => $classId,
        ])->assertStatus(422);

        // Second member registration should fail due to capacity limit (capacity = 1)
        $secondMember = Member::create(['name' => 'Андрій', 'phone' => '99999']);
        $this->postJson('/api/registrations', [
            'member_id' => $secondMember->member_id,
            'class_id'  => $classId,
        ])->assertStatus(422);

        // Show registration
        $this->getJson("/api/registrations/{$member->member_id}/{$classId}")
            ->assertStatus(200);

        // Cancel registration
        $this->deleteJson("/api/registrations/{$member->member_id}/{$classId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('registrations', [
            'member_id' => $member->member_id,
            'class_id'  => $classId,
        ]);
    }
}
