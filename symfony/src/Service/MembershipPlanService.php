<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MembershipPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class MembershipPlanService
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

        $qb = $this->entityManager->createQueryBuilder()->from(MembershipPlan::class, 'p');

        if (isset($filters['name'])) {
            $qb->andWhere('p.name LIKE :name')->setParameter('name', '%'.$filters['name'].'%');
        }

        if (isset($filters['price_min'])) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $filters['price_max']);
        }

        if (isset($filters['duration_min'])) {
            $qb->andWhere('p.durationDays >= :durationMin')->setParameter('durationMin', $filters['duration_min']);
        }

        if (isset($filters['duration_max'])) {
            $qb->andWhere('p.durationDays <= :durationMax')->setParameter('durationMax', $filters['duration_max']);
        }

        if (isset($filters['is_active'])) {
            $qb->andWhere('p.isActive = :active')->setParameter('active', (bool) $filters['is_active']);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $sortMap = [
            'plan_id' => 'p.id',
            'name' => 'p.name',
            'duration_days' => 'p.durationDays',
            'price' => 'p.price',
            'is_active' => 'p.isActive',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'plan_id'] ?? 'p.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, MembershipPlan> $plans */
        $plans = $qb->select('p')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        foreach ($plans as $plan) {
            $plan->setSubscriptionsCount($plan->getSubscriptions()->count());
        }

        return Paginator::paginate(
            array_map(static fn (MembershipPlan $plan): array => $plan->toArray(), $plans),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): MembershipPlan
    {
        $plan = new MembershipPlan();
        $plan->setName($data['name']);
        $plan->setDurationDays((int) $data['duration_days']);
        $plan->setPrice((string) $data['price']);
        $plan->setDescription($data['description'] ?? null);
        $plan->setIsActive($data['is_active'] ?? true);

        $this->entityManager->persist($plan);
        $this->entityManager->flush();

        return $plan;
    }

    public function update(MembershipPlan $plan, array $data): MembershipPlan
    {
        if (isset($data['name'])) {
            $plan->setName($data['name']);
        }

        if (isset($data['duration_days'])) {
            $plan->setDurationDays((int) $data['duration_days']);
        }

        if (isset($data['price'])) {
            $plan->setPrice((string) $data['price']);
        }

        if (\array_key_exists('description', $data)) {
            $plan->setDescription($data['description']);
        }

        if (\array_key_exists('is_active', $data) && null !== $data['is_active']) {
            $plan->setIsActive((bool) $data['is_active']);
        }

        $this->entityManager->flush();

        return $plan;
    }

    public function delete(MembershipPlan $plan): void
    {
        $this->entityManager->remove($plan);
        $this->entityManager->flush();
    }
}
