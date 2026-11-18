<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Room;
use App\Validation\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class RoomService
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

        $qb = $this->entityManager->createQueryBuilder()->from(Room::class, 'r');

        if (isset($filters['name'])) {
            $qb->andWhere('r.name LIKE :name')->setParameter('name', '%'.$filters['name'].'%');
        }

        if (isset($filters['capacity_min'])) {
            $qb->andWhere('r.capacity >= :capacityMin')->setParameter('capacityMin', $filters['capacity_min']);
        }

        if (isset($filters['capacity_max'])) {
            $qb->andWhere('r.capacity <= :capacityMax')->setParameter('capacityMax', $filters['capacity_max']);
        }

        if (isset($filters['with_equipment'])) {
            $qb->andWhere(((bool) $filters['with_equipment']) ? 'SIZE(r.equipment) > 0' : 'SIZE(r.equipment) = 0');
        }

        if (isset($filters['with_classes'])) {
            $qb->andWhere(((bool) $filters['with_classes']) ? 'SIZE(r.classes) > 0' : 'SIZE(r.classes) = 0');
        }

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        $sortMap = ['room_id' => 'r.id', 'name' => 'r.name', 'capacity' => 'r.capacity'];
        $sort = $sortMap[$filters['sort'] ?? 'room_id'] ?? 'r.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Room> $rooms */
        $rooms = $qb->select('r')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        foreach ($rooms as $room) {
            $room->setEquipmentCount($room->getEquipment()->count());
            $room->setClassesCount($room->getClasses()->count());
        }

        return Paginator::paginate(
            array_map(static fn (Room $room): array => $room->toArray(), $rooms),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Room
    {
        $room = new Room();
        $room->setName($data['name']);
        $room->setCapacity($data['capacity'] ?? 20);
        $room->setDescription($data['description'] ?? null);

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    public function update(Room $room, array $data): Room
    {
        if (\array_key_exists('capacity', $data) && null !== $data['capacity']) {
            $largestClass = (int) ($this->entityManager
                ->createQuery('SELECT MAX(c.capacity) FROM App\Entity\GymClass c WHERE c.room = :room')
                ->setParameter('room', $room)
                ->getSingleScalarResult() ?? 0);

            if ((int) $data['capacity'] < $largestClass) {
                throw new ValidationException([
                    'capacity' => ["Room capacity cannot be lower than the largest scheduled class ({$largestClass})."],
                ]);
            }

            $room->setCapacity((int) $data['capacity']);
        }

        if (isset($data['name'])) {
            $room->setName($data['name']);
        }

        if (\array_key_exists('description', $data)) {
            $room->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return $room;
    }

    public function delete(Room $room): void
    {
        $this->entityManager->remove($room);
        $this->entityManager->flush();
    }
}
