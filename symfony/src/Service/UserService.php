<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Member;
use App\Entity\User;
use App\Enum\Role;
use App\Validation\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
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

        $sortMap = [
            'id' => 'u.id',
            'name' => 'u.name',
            'email' => 'u.email',
            'role' => 'u.role',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'id'] ?? 'u.id';
        $direction = 'desc' === ($filters['direction'] ?? 'asc') ? 'DESC' : 'ASC';

        $qb = $this->entityManager->createQueryBuilder()
            ->from(User::class, 'u');

        if (isset($filters['role'])) {
            $qb->andWhere('u.role = :role')->setParameter('role', $filters['role']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere('u.name LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        $total = (int) (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        $users = $qb->select('u')
            ->orderBy($sort, $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return Paginator::paginate(
            array_map(static fn (User $user): array => $user->toArray(withMember: true), $users),
            $total,
            $request,
            $perPage,
            $page,
        );
    }

    /**
     * Create a user with an explicit role, provisioning a member profile
     * for client accounts.
     */
    public function create(array $data): User
    {
        return $this->entityManager->wrapInTransaction(function () use ($data): User {
            $user = new User();
            $user->setName($data['name']);
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setRole(Role::from($data['role']));
            $this->entityManager->persist($user);

            if (Role::Client === $user->getRole()) {
                $member = new Member();
                $member->setUser($user);
                $member->setName($user->getName());
                $member->setPhone($data['phone'] ?? null);
                $member->setJoinDate(new \DateTimeImmutable('today'));
                $user->setMember($member);
                $this->entityManager->persist($member);
            }

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        if (isset($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $this->entityManager->flush();

        return $user;
    }

    public function changeRole(User $user, string $role): User
    {
        $user->setRole(Role::from($role));
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Delete a user, refusing to let an actor delete their own account.
     */
    public function delete(User $target, User $actor): void
    {
        if ($target->getId() === $actor->getId()) {
            throw new ValidationException(['user' => ['You cannot delete your own account.']]);
        }

        $this->entityManager->remove($target);
        $this->entityManager->flush();
    }
}
