<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Equipment;
use App\Entity\Room;
use App\Enum\EquipmentCondition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class EquipmentService
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

        $qb = $this->entityManager->createQueryBuilder()->from(Equipment::class, 'e')->leftJoin('e.room', 'room');

        if (isset($filters['name'])) {
            $qb->andWhere('e.name LIKE :name')->setParameter('name', '%'.$filters['name'].'%');
        }

        if (isset($filters['condition'])) {
            $qb->andWhere('e.condition = :condition')->setParameter('condition', $filters['condition']);
        }

        if (isset($filters['room_id'])) {
            $qb->andWhere('room.id = :roomId')->setParameter('roomId', $filters['room_id']);
        }

        if (isset($filters['quantity_min'])) {
            $qb->andWhere('e.quantity >= :quantityMin')->setParameter('quantityMin', $filters['quantity_min']);
        }

        $total = (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        $sortMap = [
            'equipment_id' => 'e.id',
            'name' => 'e.name',
            'quantity' => 'e.quantity',
            'condition' => 'e.condition',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'equipment_id'] ?? 'e.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Equipment> $items */
        $items = $qb->select('e')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return Paginator::paginate(
            array_map(static fn (Equipment $equipment): array => $equipment->toArray(withRoom: true), $items),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Equipment
    {
        $equipment = new Equipment();
        $equipment->setName($data['name']);
        $equipment->setQuantity($data['quantity'] ?? 1);
        $equipment->setCondition(isset($data['condition'])
            ? EquipmentCondition::from($data['condition'])
            : EquipmentCondition::Good);
        $equipment->setRoom($this->resolveRoom($data['room_id'] ?? null));

        $this->entityManager->persist($equipment);
        $this->entityManager->flush();

        return $equipment;
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        if (isset($data['name'])) {
            $equipment->setName($data['name']);
        }

        if (\array_key_exists('quantity', $data) && null !== $data['quantity']) {
            $equipment->setQuantity((int) $data['quantity']);
        }

        if (isset($data['condition'])) {
            $equipment->setCondition(EquipmentCondition::from($data['condition']));
        }

        if (\array_key_exists('room_id', $data)) {
            $equipment->setRoom($this->resolveRoom($data['room_id']));
        }

        $this->entityManager->flush();

        return $equipment;
    }

    public function delete(Equipment $equipment): void
    {
        $this->entityManager->remove($equipment);
        $this->entityManager->flush();
    }

    private function resolveRoom(?int $roomId): ?Room
    {
        return null === $roomId ? null : $this->entityManager->find(Room::class, $roomId);
    }
}
