<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use App\Entity\MembershipPlan;
use App\Entity\Subscription;
use App\Validation\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionService
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
            ->from(Subscription::class, 's')
            ->leftJoin('s.member', 'm')
            ->leftJoin('s.plan', 'p')
            ->addSelect('m', 'p');

        if (isset($filters['member_id'])) {
            $qb->andWhere('m.id = :memberId')->setParameter('memberId', $filters['member_id']);
        }

        if (isset($filters['plan_id'])) {
            $qb->andWhere('p.id = :planId')->setParameter('planId', $filters['plan_id']);
        }

        if (isset($filters['price_min'])) {
            $qb->andWhere('s.price >= :priceMin')->setParameter('priceMin', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $qb->andWhere('s.price <= :priceMax')->setParameter('priceMax', $filters['price_max']);
        }

        if (isset($filters['start_date_from'])) {
            $qb->andWhere('s.startDate >= :startFrom')->setParameter('startFrom', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to'])) {
            $qb->andWhere('s.startDate <= :startTo')->setParameter('startTo', $filters['start_date_to']);
        }

        if (isset($filters['end_date_from'])) {
            $qb->andWhere('s.endDate >= :endFrom')->setParameter('endFrom', $filters['end_date_from']);
        }

        if (isset($filters['end_date_to'])) {
            $qb->andWhere('s.endDate <= :endTo')->setParameter('endTo', $filters['end_date_to']);
        }

        if (isset($filters['active'])) {
            $today = new \DateTimeImmutable('today');
            $qb->andWhere(((bool) $filters['active']) ? 's.endDate >= :today' : 's.endDate < :today')
                ->setParameter('today', $today);
        }

        $total = (int) (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        $sortMap = [
            'sub_id' => 's.id',
            'member_id' => 'm.id',
            'plan_id' => 'p.id',
            'start_date' => 's.startDate',
            'end_date' => 's.endDate',
            'price' => 's.price',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'sub_id'] ?? 's.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Subscription> $subscriptions */
        $subscriptions = $qb->select('s', 'm', 'p')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return Paginator::paginate(
            array_map(
                static fn (Subscription $subscription): array => $subscription->toArray(withMember: true, withPlan: true),
                $subscriptions,
            ),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Subscription
    {
        $data = $this->applyPlan($data);

        $member = $this->entityManager->find(Member::class, $data['member_id']);
        $plan = isset($data['plan_id']) ? $this->entityManager->find(MembershipPlan::class, $data['plan_id']) : null;

        $subscription = new Subscription();
        $subscription->setMember($member);
        $subscription->setPlan($plan);
        $subscription->setStartDate($data['start_date']);
        $subscription->setEndDate($data['end_date']);
        $subscription->setPrice($data['price'] ?? null);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $data = $this->applyPlan($data, $subscription);

        if (\array_key_exists('member_id', $data)) {
            $subscription->setMember($this->entityManager->find(Member::class, $data['member_id']));
        }

        if (\array_key_exists('plan_id', $data)) {
            $subscription->setPlan(
                null === $data['plan_id']
                    ? null
                    : $this->entityManager->find(MembershipPlan::class, $data['plan_id']),
            );
        }

        if (isset($data['start_date'])) {
            $subscription->setStartDate($data['start_date']);
        }

        if (isset($data['end_date'])) {
            $subscription->setEndDate($data['end_date']);
        }

        if (\array_key_exists('price', $data)) {
            $subscription->setPrice($data['price']);
        }

        $this->entityManager->flush();

        return $subscription;
    }

    public function delete(Subscription $subscription): void
    {
        $this->entityManager->remove($subscription);
        $this->entityManager->flush();
    }

    /**
     * When a membership plan is chosen, derive the price and end date from it
     * unless the caller provided explicit values.
     */
    private function applyPlan(array $data, ?Subscription $subscription = null): array
    {
        if (empty($data['plan_id'])) {
            return $data;
        }

        $plan = $this->entityManager->find(MembershipPlan::class, $data['plan_id']);

        if (!$plan instanceof MembershipPlan) {
            throw new ValidationException(['plan_id' => ['The selected plan_id is invalid.']]);
        }

        if (!$plan->isActive() && null === $subscription) {
            throw new ValidationException(['plan_id' => ['This membership plan is inactive.']]);
        }

        if (!\array_key_exists('price', $data) || null === $data['price']) {
            $data['price'] = $plan->getPrice();
        }

        if (!\array_key_exists('end_date', $data) || null === $data['end_date']) {
            $startDate = $data['start_date']
                ?? $subscription?->getStartDate()
                ?? new \DateTimeImmutable('today');

            $data['end_date'] = $startDate->modify(sprintf('+%d days', $plan->getDurationDays()));
        }

        return $data;
    }
}
