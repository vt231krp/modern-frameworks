<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed one account per role. Password for all demo accounts: "password".
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
