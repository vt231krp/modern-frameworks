<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coach;
use App\Entity\GymClass;
use App\Entity\Room;
use App\Validation\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class GymClassService
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
            ->from(GymClass::class, 'c')
            ->leftJoin('c.coach', 'coach')
            ->leftJoin('c.room', 'room')
            ->addSelect('coach', 'room');

        if (isset($filters['title'])) {
            $qb->andWhere('c.title LIKE :title')->setParameter('title', '%'.$filters['title'].'%');
        }

        if (isset($filters['coach_id'])) {
            $qb->andWhere('coach.id = :coachId')->setParameter('coachId', $filters['coach_id']);
        }

        if (isset($filters['room_id'])) {
            $qb->andWhere('room.id = :roomId')->setParameter('roomId', $filters['room_id']);
        }

        if (isset($filters['class_time_from'])) {
            $qb->andWhere('c.classTime >= :timeFrom')->setParameter('timeFrom', $filters['class_time_from']);
        }

        if (isset($filters['class_time_to'])) {
            $qb->andWhere('c.classTime <= :timeTo')->setParameter('timeTo', $filters['class_time_to']);
        }

        if (isset($filters['capacity_min'])) {
            $qb->andWhere('c.capacity >= :capacityMin')->setParameter('capacityMin', $filters['capacity_min']);
        }

        if (isset($filters['capacity_max'])) {
            $qb->andWhere('c.capacity <= :capacityMax')->setParameter('capacityMax', $filters['capacity_max']);
        }

        if (isset($filters['available'])) {
            $qb->andWhere(
                ((bool) $filters['available'])
                    ? 'SIZE(c.registrations) < c.capacity'
                    : 'SIZE(c.registrations) >= c.capacity',
            );
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $sortMap = [
            'class_id' => 'c.id',
            'title' => 'c.title',
            'class_time' => 'c.classTime',
            'capacity' => 'c.capacity',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'class_id'] ?? 'c.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, GymClass> $classes */
        $classes = $qb->select('c', 'coach', 'room')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        foreach ($classes as $class) {
            $class->setMembersCount($class->getRegistrations()->count());
        }

        return Paginator::paginate(
            array_map(
                static fn (GymClass $class): array => $class->toArray(withCoach: true, withRoom: true),
                $classes,
            ),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): GymClass
    {
        $this->assertFitsRoom($data['room_id'] ?? null, $data['capacity'] ?? null);

        $class = new GymClass();
        $class->setTitle($data['title']);
        $class->setCoach(isset($data['coach_id']) ? $this->entityManager->find(Coach::class, $data['coach_id']) : null);
        $class->setRoom(isset($data['room_id']) ? $this->entityManager->find(Room::class, $data['room_id']) : null);
        $class->setClassTime($data['class_time']);
        $class->setCapacity($data['capacity'] ?? 20);

        $this->entityManager->persist($class);
        $this->entityManager->flush();

        return $class;
    }

    public function update(GymClass $class, array $data): GymClass
    {
        $roomId = \array_key_exists('room_id', $data) ? $data['room_id'] : $class->getRoom()?->getId();
        $capacity = \array_key_exists('capacity', $data) && null !== $data['capacity']
            ? $data['capacity']
            : $class->getCapacity();

        $this->assertFitsRoom($roomId, $capacity);

        if (isset($data['title'])) {
            $class->setTitle($data['title']);
        }

        if (\array_key_exists('coach_id', $data)) {
            $class->setCoach(null === $data['coach_id'] ? null : $this->entityManager->find(Coach::class, $data['coach_id']));
        }

        if (\array_key_exists('room_id', $data)) {
            $class->setRoom(null === $data['room_id'] ? null : $this->entityManager->find(Room::class, $data['room_id']));
        }

        if (isset($data['class_time'])) {
            $class->setClassTime($data['class_time']);
        }

        if (\array_key_exists('capacity', $data) && null !== $data['capacity']) {
            $class->setCapacity((int) $data['capacity']);
        }

        $this->entityManager->flush();

        return $class;
    }

    public function delete(GymClass $class): void
    {
        $this->entityManager->remove($class);
        $this->entityManager->flush();
    }

    /**
     * A class cannot seat more members than the room it is scheduled in.
     */
    private function assertFitsRoom(?int $roomId, ?int $capacity): void
    {
        if (empty($roomId) || empty($capacity)) {
            return;
        }

        $room = $this->entityManager->find(Room::class, $roomId);

        if ($room instanceof Room && (int) $capacity > $room->getCapacity()) {
            throw new ValidationException([
                'capacity' => [sprintf(
                    'Class capacity (%d) exceeds room capacity (%d).',
                    $capacity,
                    $room->getCapacity(),
                )],
            ]);
        }
    }
}
