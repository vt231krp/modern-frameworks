<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attendance;
use App\Entity\Member;
use App\Entity\GymClass;
use App\Enum\AttendanceStatus;
use App\Validation\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class AttendanceService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function paginate(array $filters, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = (int) ($filters['per_page'] ?? 15);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Attendance::class, 'a')
            ->leftJoin('a.member', 'm')
            ->leftJoin('a.gymClass', 'c')
            ->addSelect('m', 'c');

        if (isset($filters['member_id'])) {
            $qb->andWhere('m.id = :memberId')->setParameter('memberId', $filters['member_id']);
        }

        if (isset($filters['class_id'])) {
            $qb->andWhere('c.id = :classId')->setParameter('classId', $filters['class_id']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('a.status = :status')->setParameter('status', $filters['status']);
        }

        if (isset($filters['checked_in_from'])) {
            $qb->andWhere('a.checkedInAt >= :checkedFrom')->setParameter('checkedFrom', $filters['checked_in_from']);
        }

        if (isset($filters['checked_in_to'])) {
            $qb->andWhere('a.checkedInAt <= :checkedTo')->setParameter('checkedTo', $filters['checked_in_to']);
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $sortMap = [
            'attendance_id' => 'a.id',
            'status' => 'a.status',
            'checked_in_at' => 'a.checkedInAt',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'attendance_id'] ?? 'a.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Attendance> $attendances */
        $attendances = $qb->select('a', 'm', 'c')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return Paginator::paginate(
            array_map(
                static fn (Attendance $attendance): array => $attendance->toArray(withMember: true, withClass: true),
                $attendances,
            ),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Attendance
    {
        $this->assertRegistered((int) $data['member_id'], (int) $data['class_id']);

        $exists = (int) $this->entityManager
            ->createQuery(
                'SELECT COUNT(a.id) FROM App\Entity\Attendance a
                 JOIN a.member m JOIN a.gymClass c
                 WHERE m.id = :memberId AND c.id = :classId',
            )
            ->setParameter('memberId', $data['member_id'])
            ->setParameter('classId', $data['class_id'])
            ->getSingleScalarResult();

        if ($exists > 0) {
            throw new ValidationException([
                'class_id' => ['Attendance for this member and class already exists.'],
            ]);
        }

        $status = isset($data['status']) ? AttendanceStatus::from($data['status']) : AttendanceStatus::Present;

        $attendance = new Attendance();
        $attendance->setMember($this->entityManager->find(Member::class, $data['member_id']));
        $attendance->setGymClass($this->entityManager->find(GymClass::class, $data['class_id']));
        $attendance->setStatus($status);
        $attendance->setCheckedInAt(
            AttendanceStatus::Absent !== $status
                ? ($data['checked_in_at'] ?? new \DateTimeImmutable())
                : ($data['checked_in_at'] ?? null),
        );
        $attendance->setNotes($data['notes'] ?? null);

        $this->entityManager->persist($attendance);
        $this->entityManager->flush();

        return $attendance;
    }

    public function update(Attendance $attendance, array $data): Attendance
    {
        $status = isset($data['status'])
            ? AttendanceStatus::from($data['status'])
            : $attendance->getStatus();

        $checkedInAt = \array_key_exists('checked_in_at', $data)
            ? $data['checked_in_at']
            : $attendance->getCheckedInAt();

        if (AttendanceStatus::Absent !== $status && null === $checkedInAt) {
            $checkedInAt = new \DateTimeImmutable();
        }

        $attendance->setStatus($status);
        $attendance->setCheckedInAt($checkedInAt);

        if (\array_key_exists('notes', $data)) {
            $attendance->setNotes($data['notes']);
        }

        $this->entityManager->flush();

        return $attendance;
    }

    public function delete(Attendance $attendance): void
    {
        $this->entityManager->remove($attendance);
        $this->entityManager->flush();
    }

    /**
     * Attendance only makes sense for a member who is registered for the class.
     */
    private function assertRegistered(int $memberId, int $classId): void
    {
        $registered = (int) $this->entityManager
            ->createQuery(
                'SELECT COUNT(m.id) FROM App\Entity\Registration r
                 JOIN r.member m JOIN r.gymClass c
                 WHERE m.id = :memberId AND c.id = :classId',
            )
            ->setParameter('memberId', $memberId)
            ->setParameter('classId', $classId)
            ->getSingleScalarResult();

        if (0 === $registered) {
            throw new ValidationException([
                'member_id' => ['Member is not registered for this class.'],
            ]);
        }
    }
}
