<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
readonly class JWTCreatedListener
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();
        $request = $this->requestStack->getCurrentRequest();

        if (!$user instanceof User) {
            return;
        }

        // Ajouter des données utilisateur personnalisées au JWT
        $payload['user_id'] = $user->getId();
        $payload['email'] = $user->getEmail();
        $payload['first_name'] = $user->getFirstName();
        $payload['last_name'] = $user->getLastName();
        $payload['roles'] = $user->getRoles();
        $payload['is_verified'] = $user->isVerified();

        // Ajouter des informations sur la session
        if ($request) {
            $payload['ip'] = $request->getClientIp();
            $payload['user_agent'] = $request->headers->get('User-Agent');
        }

        // Ajouter un timestamp de création
        $payload['created_at'] = time();

        // Définir une expiration personnalisée si nécessaire
        // $payload['exp'] = time() + 3600; // 1 heure

        $event->setData($payload);
    }
}
