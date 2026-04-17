<?php
namespace App\EventListener;

use App\Repository\BlockedTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
final class JwtAuthenticatedListener
{
    public function __construct(private BlockedTokenRepository $blocked) {}

    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        $jti = $event->getPayload()['jti'] ?? null;
        if ($jti && $this->blocked->isBlocked($jti)) {
            throw new CustomUserMessageAuthenticationException('Token revoked');
        }
    }
}