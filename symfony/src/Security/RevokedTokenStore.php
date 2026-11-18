<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RevokedToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persistent JWT blacklist used by logout and refresh.
 */
class RevokedTokenStore
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function revoke(string $jti, \DateTimeImmutable $expiresAt): void
    {
        if (null !== $this->entityManager->find(RevokedToken::class, $jti)) {
            return;
        }

        $this->entityManager
            ->createQuery('DELETE FROM '.RevokedToken::class.' t WHERE t.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->execute();

        $this->entityManager->persist(new RevokedToken($jti, $expiresAt));
        $this->entityManager->flush();
    }

    public function isRevoked(string $jti): bool
    {
        return null !== $this->entityManager->find(RevokedToken::class, $jti);
    }
}
