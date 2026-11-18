<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds a unique "jti" claim so individual tokens can be revoked on logout or
 * refresh (the Laravel implementation gets this from jwt-auth's blacklist).
 */
#[AsEventListener(event: Events::JWT_CREATED)]
class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $data = $event->getData();

        if (!isset($data['jti'])) {
            $data['jti'] = bin2hex(random_bytes(16));
        }

        $event->setData($data);
    }
}
