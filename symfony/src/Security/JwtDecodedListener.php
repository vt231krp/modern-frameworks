<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Rejects tokens whose "jti" has been blacklisted by logout/refresh.
 */
#[AsEventListener(event: Events::JWT_DECODED)]
class JwtDecodedListener
{
    public function __construct(private readonly RevokedTokenStore $store)
    {
    }

    public function __invoke(JWTDecodedEvent $event): void
    {
        $jti = $event->getPayload()['jti'] ?? null;

        if (\is_string($jti) && $this->store->isRevoked($jti)) {
            $event->markAsInvalid();
        }
    }
}
