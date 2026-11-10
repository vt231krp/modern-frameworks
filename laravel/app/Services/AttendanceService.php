<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Registration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Attendance::with(['member', 'gymClass'])
            ->when(isset($filters['member_id']), fn ($q) => $q->where('member_id', $filters['member_id']))
            ->when(isset($filters['class_id']), fn ($q) => $q->where('class_id', $filters['class_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['checked_in_from']), fn ($q) => $q->whereDate('checked_in_at', '>=', $filters['checked_in_from']))
            ->when(isset($filters['checked_in_to']), fn ($q) => $q->whereDate('checked_in_at', '<=', $filters['checked_in_to']))
            ->orderBy($filters['sort'] ?? 'attendance_id', $filters['direction'] ?? 'asc')
            ->paginate(max(1, min($perPage, 100)))
            ->appends($filters);
    }

    public function create(array $data): Attendance
    {
        $this->assertRegistered((int) $data['member_id'], (int) $data['class_id']);

        $exists = Attendance::where('member_id', $data['member_id'])
            ->where('class_id', $data['class_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'class_id' => 'Attendance for this member and class already exists.',
            ]);
        }

        if (($data['status'] ?? 'present') !== 'absent' && empty($data['checked_in_at'])) {
            $data['checked_in_at'] = now();
        }

        return Attendance::create($data);
    }

    public function update(Attendance $attendance, array $data): Attendance
    {
        $status = $data['status'] ?? $attendance->status;

        if ($status !== 'absent'
            && empty($data['checked_in_at'])
            && is_null($attendance->checked_in_at)) {
            $data['checked_in_at'] = now();
        }

        $attendance->update($data);

        return $attendance;
    }

    public function delete(Attendance $attendance): void
    {
        $attendance->delete();
    }

    /**
     * Attendance only makes sense for a member who is registered for the class.
     */
    private function assertRegistered(int $memberId, int $classId): void
    {
        $registered = Registration::where('member_id', $memberId)
            ->where('class_id', $classId)
            ->exists();

        if (! $registered) {
            throw ValidationException::withMessages([
                'member_id' => 'Member is not registered for this class.',
            ]);
        }
    }
}
