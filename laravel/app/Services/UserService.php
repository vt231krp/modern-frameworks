<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return User::query()
            ->when(isset($filters['role']), fn ($q) => $q->where('role', $filters['role']))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            }))
            ->orderBy($filters['sort'] ?? 'id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    /**
     * Create a user with an explicit role, provisioning a member profile
     * for client accounts.
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = $data['password'];
            $user->role = Role::from($data['role']);
            $user->save();

            if ($user->role === Role::Client) {
                $member = new Member;
                $member->user_id = $user->id;
                $member->name = $user->name;
                $member->phone = $data['phone'] ?? null;
                $member->join_date = now()->toDateString();
                $member->save();
            }

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function changeRole(User $user, string $role): User
    {
        $user->role = Role::from($role);
        $user->save();

        return $user;
    }

    /**
     * Delete a user, refusing to let an actor delete their own account.
     */
    public function delete(User $target, User $actor): void
    {
        if ($target->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $target->delete();
    }
}
