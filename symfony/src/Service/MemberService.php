<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class MemberService
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

        $qb = $this->entityManager->createQueryBuilder()->from(Member::class, 'm');

        if (isset($filters['name'])) {
            $qb->andWhere('m.name LIKE :name')->setParameter('name', '%'.$filters['name'].'%');
        }

        if (isset($filters['phone'])) {
            $qb->andWhere('m.phone LIKE :phone')->setParameter('phone', '%'.$filters['phone'].'%');
        }

        if (isset($filters['join_date_from'])) {
            $qb->andWhere('m.joinDate >= :joinFrom')->setParameter('joinFrom', $filters['join_date_from']);
        }

        if (isset($filters['join_date_to'])) {
            $qb->andWhere('m.joinDate <= :joinTo')->setParameter('joinTo', $filters['join_date_to']);
        }

        if (isset($filters['active'])) {
            $today = new \DateTimeImmutable('today');
            $exists = 'EXISTS (SELECT s.id FROM App\Entity\Subscription s WHERE s.member = m AND s.endDate >= :today)';

            $qb->andWhere(((bool) $filters['active']) ? $exists : 'NOT '.$exists)
                ->setParameter('today', $today);
        }

        $total = (int) (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();

        $sortMap = ['member_id' => 'm.id', 'name' => 'm.name', 'join_date' => 'm.joinDate'];
        $sort = $sortMap[$filters['sort'] ?? 'member_id'] ?? 'm.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Member> $members */
        $members = $qb->select('m')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        foreach ($members as $member) {
            $member->setSubscriptionsCount($member->getSubscriptions()->count());
            $member->setClassesCount($member->getRegistrations()->count());
        }

        return Paginator::paginate(
            array_map(static fn (Member $member): array => $member->toArray(), $members),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    public function create(array $data): Member
    {
        $member = new Member();
        $member->setName($data['name']);
        $member->setPhone($data['phone'] ?? null);
        $member->setJoinDate($data['join_date'] ?? new \DateTimeImmutable('today'));

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $member;
    }

    public function update(Member $member, array $data): Member
    {
        if (isset($data['name'])) {
            $member->setName($data['name']);
        }

        if (\array_key_exists('phone', $data)) {
            $member->setPhone($data['phone']);
        }

        if (isset($data['join_date'])) {
            $member->setJoinDate($data['join_date']);
        }

        $this->entityManager->flush();

        return $member;
    }

    public function delete(Member $member): void
    {
        $this->entityManager->remove($member);
        $this->entityManager->flush();
    }
}
