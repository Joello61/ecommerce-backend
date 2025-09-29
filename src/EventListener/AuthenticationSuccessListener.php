<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
readonly class AuthenticationSuccessListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        $data = $event->getData();
        $request = $this->requestStack->getCurrentRequest();

        if (!$user instanceof User) {
            return;
        }

        // Mettre à jour la date de dernière connexion
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        // Ajouter des informations utilisateur à la réponse JWT
        $data['user'] = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
        ];

        // Ajouter des informations sur le panier si il existe
        if ($user->getCart() && !$user->getCart()->isEmpty()) {
            $data['cart'] = [
                'id' => $user->getCart()->getId(),
                'totalItems' => $user->getCart()->getTotalItems(),
                'totalQuantity' => $user->getCart()->getTotalQuantity(),
            ];
        }

        $event->setData($data);

        // Logger la connexion réussie
        $this->logSuccessfulLogin($user, $request);
    }

    private function logSuccessfulLogin(User $user, $request): void
    {
        $logData = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        if ($request) {
            $logData['ip'] = $request->getClientIp();
            $logData['user_agent'] = $request->headers->get('User-Agent');
            $logData['referer'] = $request->headers->get('Referer');
        }

        $this->logger->info('Connexion utilisateur réussie', $logData);
    }
}
