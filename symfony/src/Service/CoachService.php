<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coach;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class CoachService
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

        $qb = $this->entityManager->createQueryBuilder()->from(Coach::class, 'c');

        if (isset($filters['name'])) {
            $qb->andWhere('c.name LIKE :name')->setParameter('name', '%'.$filters['name'].'%');
        }

        if (isset($filters['phone'])) {
            $qb->andWhere('c.phone LIKE :phone')->setParameter('phone', '%'.$filters['phone'].'%');
        }

        if (isset($filters['with_classes'])) {
            $qb->andWhere(((bool) $filters['with_classes']) ? 'SIZE(c.classes) > 0' : 'SIZE(c.classes) = 0');
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $sortMap = ['coach_id' => 'c.id', 'name' => 'c.name'];
        $sort = $sortMap[$filters['sort'] ?? 'coach_id'] ?? 'c.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Coach> $coaches */
        $coaches = $qb->select('c')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        foreach ($coaches as $coach) {
            $coach->setClassesCount($coach->getClasses()->count());
        }

        return Paginator::paginate(
            array_map(static fn (Coach $coach): array => $coach->toArray(), $coaches),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Coach
    {
        $coach = new Coach();
        $coach->setName($data['name']);
        $coach->setPhone($data['phone'] ?? null);

        $this->entityManager->persist($coach);
        $this->entityManager->flush();

        return $coach;
    }

    public function update(Coach $coach, array $data): Coach
    {
        if (isset($data['name'])) {
            $coach->setName($data['name']);
        }

        if (\array_key_exists('phone', $data)) {
            $coach->setPhone($data['phone']);
        }

        $this->entityManager->flush();

        return $coach;
    }

    public function delete(Coach $coach): void
    {
        $this->entityManager->remove($coach);
        $this->entityManager->flush();
    }
}
