<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GymClass;
use App\Entity\Member;
use App\Entity\Registration;
use App\Entity\User;
use App\Enum\Role;
use App\Validation\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class RegistrationService
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
            ->from(Registration::class, 'r')
            ->leftJoin('r.member', 'm')
            ->leftJoin('r.gymClass', 'c')
            ->addSelect('m', 'c');

        if (isset($filters['member_id'])) {
            $qb->andWhere('m.id = :memberId')->setParameter('memberId', $filters['member_id']);
        }

        if (isset($filters['class_id'])) {
            $qb->andWhere('c.id = :classId')->setParameter('classId', $filters['class_id']);
        }

        $total = (int) (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();

        $sortMap = ['member_id' => 'm.id', 'class_id' => 'c.id'];
        $sort = $sortMap[$filters['sort'] ?? 'member_id'] ?? 'm.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        /** @var array<int, Registration> $registrations */
        $registrations = $qb->select('r', 'm', 'c')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return Paginator::paginate(
            array_map(
                static fn (Registration $registration): array => $registration->toArray(withMember: true, withClass: true),
                $registrations,
            ),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    /**
     * Register a member for a class, enforcing ownership, duplicates and capacity.
     */
    public function create(array $data, User $user): Registration
    {
        $this->assertOwnership((int) $data['member_id'], $user);
        $this->assertNotRegistered((int) $data['member_id'], (int) $data['class_id']);
        $this->assertCapacityAvailable((int) $data['class_id']);

        $registration = new Registration();
        $registration->setMember($this->entityManager->find(Member::class, $data['member_id']));
        $registration->setGymClass($this->entityManager->find(GymClass::class, $data['class_id']));

        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        return $registration;
    }

    /**
     * Move an existing registration to another class.
     */
    public function update(int $memberId, int $classId, int $newClassId, User $user): Registration
    {
        $registration = $this->find($memberId, $classId);

        $this->assertOwnership($memberId, $user);

        if ($newClassId === $classId) {
            return $registration;
        }

        $this->assertNotRegistered($memberId, $newClassId);
        $this->assertCapacityAvailable($newClassId);

        $this->entityManager->wrapInTransaction(function () use ($memberId, $classId, $newClassId): void {
            $this->entityManager->remove($this->find($memberId, $classId));
            $this->entityManager->flush();

            $registration = new Registration();
            $registration->setMember($this->entityManager->find(Member::class, $memberId));
            $registration->setGymClass($this->entityManager->find(GymClass::class, $newClassId));
            $this->entityManager->persist($registration);
        });

        return $this->find($memberId, $newClassId);
    }

    public function find(int $memberId, int $classId): Registration
    {
        $registration = $this->entityManager
            ->createQueryBuilder()
            ->from(Registration::class, 'r')
            ->leftJoin('r.member', 'm')
            ->leftJoin('r.gymClass', 'c')
            ->addSelect('m', 'c')
            ->where('m.id = :memberId')
            ->andWhere('c.id = :classId')
            ->setParameter('memberId', $memberId)
            ->setParameter('classId', $classId)
            ->select('r', 'm', 'c')
            ->getQuery()
            ->getOneOrNullResult();

        if (!$registration instanceof Registration) {
            throw new NotFoundHttpException('Resource not found.');
        }

        return $registration;
    }

    public function delete(int $memberId, int $classId): void
    {
        $registration = $this->find($memberId, $classId);

        $this->entityManager->remove($registration);
        $this->entityManager->flush();
    }

    /**
     * A client may only operate on their own member profile.
     */
    private function assertOwnership(int $memberId, User $user): void
    {
        if (Role::Client !== $user->getRole()) {
            return;
        }

        $member = $this->entityManager->find(Member::class, $memberId);

        if (!$member instanceof Member || $member->getUser()?->getId() !== $user->getId()) {
            throw new AccessDeniedException('You can only register your own member profile.');
        }
    }

    private function assertNotRegistered(int $memberId, int $classId): void
    {
        $exists = (int) $this->entityManager
            ->createQuery(
                'SELECT COUNT(m.id) FROM App\Entity\Registration r
                 JOIN r.member m JOIN r.gymClass c
                 WHERE m.id = :memberId AND c.id = :classId',
            )
            ->setParameter('memberId', $memberId)
            ->setParameter('classId', $classId)
            ->getSingleScalarResult();

        if ($exists > 0) {
            throw new ValidationException([
                'class_id' => ['Member is already registered for this class.'],
            ]);
        }
    }

    private function assertCapacityAvailable(int $classId): void
    {
        $class = $this->entityManager->find(GymClass::class, $classId);

        if (!$class instanceof GymClass) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $currentRegistrations = (int) $this->entityManager
            ->createQuery(
                'SELECT COUNT(c.id) FROM App\Entity\Registration r
                 JOIN r.gymClass c WHERE c.id = :classId',
            )
            ->setParameter('classId', $classId)
            ->getSingleScalarResult();

        if ($currentRegistrations >= $class->getCapacity()) {
            throw new ValidationException([
                'class_id' => [sprintf(
                    'This class has already reached its maximum capacity (%d).',
                    $class->getCapacity(),
                )],
            ]);
        }
    }
}
