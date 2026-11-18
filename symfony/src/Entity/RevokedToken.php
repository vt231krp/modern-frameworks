<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * JWT identifiers invalidated by logout/refresh. Mirrors the blacklist used by
 * php-open-source-saver/jwt-auth in the Laravel implementation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'revoked_tokens')]
#[ORM\Index(name: 'revoked_tokens_expires_at_index', columns: ['expires_at'])]
class RevokedToken
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $jti;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $jti, \DateTimeImmutable $expiresAt)
    {
        $this->jti = $jti;
        $this->expiresAt = $expiresAt;
    }

    public function getJti(): string
    {
        return $this->jti;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
