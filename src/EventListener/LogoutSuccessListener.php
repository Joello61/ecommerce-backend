<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_invalid', method: 'onJWTInvalid')]
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_not_found', method: 'onJWTNotFound')]
readonly class LogoutSuccessListener
{
    public function __construct(
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $this->logger->info('JWT invalide détecté', [
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'exception' => $event->getException()->getMessage(),
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Personnaliser la réponse d'erreur
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Token invalide. Veuillez vous reconnecter.',
            'code' => 'INVALID_TOKEN',
        ], 401);

        $event->setResponse($response);
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $this->logger->info('JWT manquant', [
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'requested_url' => $request?->getRequestUri(),
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Personnaliser la réponse d'erreur
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Token d\'authentification requis.',
            'code' => 'TOKEN_NOT_FOUND',
        ], 401);

        $event->setResponse($response);
    }

    /** Méthode pour logger une déconnexion manuelle réussie */
    public function logSuccessfulLogout(?User $user = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $logData = [
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        if ($user) {
            $logData['user_id'] = $user->getId();
            $logData['email'] = $user->getEmail();
        }

        if ($request) {
            $logData['ip'] = $request->getClientIp();
            $logData['user_agent'] = $request->headers->get('User-Agent');
        }

        $this->logger->info('Déconnexion utilisateur', $logData);
    }
}
