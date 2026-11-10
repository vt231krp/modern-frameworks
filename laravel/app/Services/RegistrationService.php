<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\GymClass;
use App\Models\Member;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Registration::with(['member', 'gymClass'])
            ->when(isset($filters['member_id']), fn ($q) => $q->where('member_id', $filters['member_id']))
            ->when(isset($filters['class_id']), fn ($q) => $q->where('class_id', $filters['class_id']))
            ->orderBy($filters['sort'] ?? 'member_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    /**
     * Register a member for a class, enforcing ownership, duplicates and capacity.
     */
    public function create(array $data, User $user): Registration
    {
        $this->assertOwnership((int) $data['member_id'], $user);

        $this->assertNotRegistered((int) $data['member_id'], (int) $data['class_id']);
        $this->assertCapacityAvailable((int) $data['class_id']);

        return Registration::create($data);
    }

    /**
     * Move an existing registration to another class.
     */
    public function update(int $memberId, int $classId, int $newClassId, User $user): Registration
    {
        $registration = $this->find($memberId, $classId);

        $this->assertOwnership($registration->member_id, $user);

        if ($newClassId === $classId) {
            return $registration->load(['member', 'gymClass']);
        }

        $this->assertNotRegistered($memberId, $newClassId);
        $this->assertCapacityAvailable($newClassId);

        DB::transaction(function () use ($memberId, $classId, $newClassId) {
            Registration::where('member_id', $memberId)
                ->where('class_id', $classId)
                ->delete();

            Registration::create([
                'member_id' => $memberId,
                'class_id' => $newClassId,
            ]);
        });

        return $this->find($memberId, $newClassId)->load(['member', 'gymClass']);
    }

    public function find(int $memberId, int $classId): Registration
    {
        return Registration::with(['member', 'gymClass'])
            ->where('member_id', $memberId)
            ->where('class_id', $classId)
            ->firstOrFail();
    }

    public function delete(int $memberId, int $classId): void
    {
        // Registration uses a composite key, so delete through the query
        // builder instead of Model::delete() (which expects a single key).
        $this->find($memberId, $classId);

        Registration::where('member_id', $memberId)
            ->where('class_id', $classId)
            ->delete();
    }

    /**
     * A client may only operate on their own member profile.
     */
    private function assertOwnership(int $memberId, User $user): void
    {
        if ($user->role !== Role::Client) {
            return;
        }

        $member = Member::find($memberId);

        if (! $member || $member->user_id !== $user->id) {
            throw new AuthorizationException('You can only register your own member profile.');
        }
    }

    private function assertNotRegistered(int $memberId, int $classId): void
    {
        $exists = Registration::where('member_id', $memberId)
            ->where('class_id', $classId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'class_id' => 'Member is already registered for this class.',
            ]);
        }
    }

    private function assertCapacityAvailable(int $classId): void
    {
        $class = GymClass::findOrFail($classId);

        $currentRegistrations = Registration::where('class_id', $classId)->count();

        if ($currentRegistrations >= $class->capacity) {
            throw ValidationException::withMessages([
                'class_id' => 'This class has already reached its maximum capacity ('.$class->capacity.').',
            ]);
        }
    }
}
