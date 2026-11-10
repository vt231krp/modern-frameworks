<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Equipment;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed one account per role plus representative data for every domain table.
     * Password for all demo accounts: "password".
     */
    public function run(): void
    {
        $admin = $this->upsertUser('Admin User', 'admin@gym.test', Role::Admin);
        $this->upsertUser('Manager User', 'manager@gym.test', Role::Manager);

        $client = $this->upsertUser('Client User', 'client@gym.test', Role::Client);

        $member = Member::firstOrNew(['user_id' => $client->id]);
        $member->name = 'Client User';
        $member->phone = '+380991234567';
        $member->join_date = $member->join_date ?? now()->toDateString();
        $member->save();

        $monthly = MembershipPlan::firstOrCreate(
            ['name' => 'Місячний'],
            ['duration_days' => 30, 'price' => 900, 'description' => 'Повний доступ на 30 днів', 'is_active' => true]
        );

        MembershipPlan::firstOrCreate(
            ['name' => 'Квартальний'],
            ['duration_days' => 90, 'price' => 2400, 'description' => 'Повний доступ на 90 днів', 'is_active' => true]
        );

        MembershipPlan::firstOrCreate(
            ['name' => 'Річний'],
            ['duration_days' => 365, 'price' => 8000, 'description' => 'Повний доступ на рік', 'is_active' => true]
        );

        $mainRoom = Room::firstOrCreate(
            ['name' => 'Головна зала'],
            ['capacity' => 30, 'description' => 'Тренажери та вільні ваги']
        );

        $yogaRoom = Room::firstOrCreate(
            ['name' => 'Зала йоги'],
            ['capacity' => 15, 'description' => 'Групові заняття та розтяжка']
        );

        Equipment::firstOrCreate(
            ['name' => 'Гантелі', 'room_id' => $mainRoom->room_id],
            ['quantity' => 20, 'condition' => 'good']
        );

        Equipment::firstOrCreate(
            ['name' => 'Килимки для йоги', 'room_id' => $yogaRoom->room_id],
            ['quantity' => 15, 'condition' => 'excellent']
        );

        Equipment::firstOrCreate(
            ['name' => 'Боксерська груша', 'room_id' => $mainRoom->room_id],
            ['quantity' => 2, 'condition' => 'fair']
        );

        $coach = Coach::firstOrCreate(
            ['name' => 'Олександр Коваленко'],
            ['phone' => '+380671112233']
        );

        $yogaCoach = Coach::firstOrCreate(
            ['name' => 'Ірина Петренко'],
            ['phone' => '+380672223344']
        );

        $boxing = GymClass::firstOrCreate(
            ['title' => 'Бокс'],
            [
                'class_time' => now()->addDays(2)->setTime(18, 0),
                'coach_id' => $coach->coach_id,
                'room_id' => $mainRoom->room_id,
                'capacity' => 20,
            ]
        );

        GymClass::firstOrCreate(
            ['title' => 'Йога'],
            [
                'class_time' => now()->addDays(3)->setTime(10, 0),
                'coach_id' => $yogaCoach->coach_id,
                'room_id' => $yogaRoom->room_id,
                'capacity' => 12,
            ]
        );

        $subscription = Subscription::firstOrCreate(
            ['member_id' => $member->member_id],
            [
                'plan_id' => $monthly->plan_id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays($monthly->duration_days)->toDateString(),
                'price' => $monthly->price,
            ]
        );

        Payment::firstOrCreate(
            ['sub_id' => $subscription->sub_id],
            [
                'payment_date' => now()->toDateString(),
                'amount' => $monthly->price,
                'method' => 'card',
                'status' => 'completed',
            ]
        );

        Registration::firstOrCreate([
            'member_id' => $member->member_id,
            'class_id' => $boxing->class_id,
        ]);

        Attendance::firstOrCreate(
            ['member_id' => $member->member_id, 'class_id' => $boxing->class_id],
            ['status' => 'present', 'checked_in_at' => now()]
        );

        $this->command?->info("Demo admin: {$admin->email} (password: password)");
    }

    private function upsertUser(string $name, string $email, Role $role): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = 'password';
        $user->role = $role;
        $user->save();

        return $user;
    }
}
