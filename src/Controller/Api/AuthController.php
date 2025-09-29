<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Auth\ChangePasswordRequest;
use App\DTO\Auth\ForgotPasswordRequest;
use App\DTO\Auth\LoginRequest;
use App\DTO\Auth\RegisterRequest;
use App\DTO\Auth\ResetPasswordRequest;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\UserService;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private const RESET_TOKEN_TTL = 3600; // 1 heure
    private const MAX_RESET_ATTEMPTS = 3;

    public function __construct(
        private readonly UserService $userService,
        private readonly EmailService $emailService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        #[MapRequestPayload]
        LoginRequest $request,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        try {
            // Récupérer l'utilisateur par email
            $user = $this->userService->getUserByEmail($request->email);

            // Vérifier le mot de passe
            if (!$passwordHasher->isPasswordValid($user, $request->password)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect',
                ], 401);
            }

            // Vérifier si le compte est actif
            if (!$user->isVerified()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Votre compte n\'est pas encore vérifié. Vérifiez votre email.',
                ], 401);
            }

            // Créer le token JWT
            $token = $this->jwtManager->create($user);

            $this->logger->info('Connexion utilisateur réussie', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            // Créer la réponse avec les données utilisateur
            $response = $this->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName(),
                        'fullName' => $user->getFullName(),
                        'roles' => $user->getRoles(),
                        'isVerified' => $user->isVerified(),
                        'createdAt' => $user->getCreatedAt(),
                        'avatarName' => $user->getAvatarName(),
                    ],
                ],
            ]);

            // Définir le token dans les cookies HttpOnly
            $response->headers->setCookie(
                Cookie::create('BEARER')
                    ->withValue($token)
                    ->withExpires(time() + 3600) // 1 heure
                    ->withPath('/')
                    ->withSecure(false) // true en production avec HTTPS
                    ->withHttpOnly(true)
                    ->withSameSite('lax'),
            );

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la connexion', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $request): JsonResponse
    {
        try {
            $userData = [
                'email' => $request->email,
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'password' => $request->password,
            ];

            $user = $this->userService->createUser($userData);

            $this->logger->info('Nouvel utilisateur inscrit', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Inscription réussie. Vous pouvez maintenant vous connecter.',
                'data' => [
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName(),
                        'fullName' => $user->getFullName(),
                        'roles' => $user->getRoles(),
                        'isVerified' => $user->isVerified(),
                        'createdAt' => $user->getCreatedAt(),
                        'avatarName' => $user->getAvatarName(),
                    ],
                ],
            ], 201);
        } catch (BadRequestHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'inscription', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'inscription',
            ], 500);
        }
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'fullName' => $user->getFullName(),
                    'roles' => $user->getRoles(),
                    'isVerified' => $user->isVerified(),
                    'createdAt' => $user->getCreatedAt(),
                    'avatarName' => $user->getAvatarName(),
                    'addresses' => $user->getAddresses()->count(),
                    'orders' => $user->getOrders()->count(),
                ],
            ],
        ]);
    }

    #[Route('/change-password', name: 'change_password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(
        #[MapRequestPayload]
        ChangePasswordRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $this->userService->changePassword(
                $user->getId(),
                $request->currentPassword,
                $request->newPassword,
            );

            $this->logger->info('Mot de passe modifié', [
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès',
            ]);
        } catch (BadRequestHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors du changement de mot de passe', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(#[MapRequestPayload] ForgotPasswordRequest $request): JsonResponse
    {
        try {
            // Vérifier le rate limiting pour éviter le spam
            $cacheKey = 'reset_attempts_' . md5($request->email);
            $attemptsItem = $this->cache->getItem($cacheKey);
            $attempts = $attemptsItem->isHit() ? $attemptsItem->get() : 0;

            if ($attempts >= self::MAX_RESET_ATTEMPTS) {
                return $this->json([
                    'success' => false,
                    'message' => 'Trop de tentatives. Réessayez dans 1 heure.',
                ], 429);
            }

            // Incrémenter les tentatives
            $attemptsItem->set($attempts + 1);
            $attemptsItem->expiresAfter(self::RESET_TOKEN_TTL);
            $this->cache->save($attemptsItem);

            try {
                $user = $this->userService->getUserByEmail($request->email);

                // Générer un token de réinitialisation sécurisé
                $resetToken = bin2hex(random_bytes(32));
                $tokenKey = 'reset_token_' . $resetToken;

                // Stocker le token avec l'email dans le cache
                $tokenItem = $this->cache->getItem($tokenKey);
                $tokenItem->set($user->getEmail());
                $tokenItem->expiresAfter(self::RESET_TOKEN_TTL);
                $this->cache->save($tokenItem);

                // Envoyer l'email
                $this->emailService->sendPasswordResetEmail($user, $resetToken);

                $this->logger->info('Demande de réinitialisation de mot de passe', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                ]);
            } catch (Exception) {
                // On ne révèle pas si l'email existe ou non pour des raisons de sécurité
                $this->logger->warning('Tentative de réinitialisation pour email inexistant', [
                    'email' => $request->email,
                ]);
            }

            // Même réponse dans tous les cas pour éviter l'énumération d'emails
            return $this->json([
                'success' => true,
                'message' => 'Si cette adresse email existe, vous recevrez un lien de réinitialisation dans quelques minutes.',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la demande de réinitialisation', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload] ResetPasswordRequest $request): JsonResponse
    {
        try {
            $tokenKey = 'reset_token_' . $request->token;
            $tokenItem = $this->cache->getItem($tokenKey);

            if (!$tokenItem->isHit()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Token invalide ou expiré',
                ], 400);
            }

            $email = $tokenItem->get();

            // Supprimer le token pour éviter la réutilisation
            $this->cache->deleteItem($tokenKey);

            // Réinitialiser le mot de passe
            $user = $this->userService->resetPassword($email, $request->password);

            $this->logger->info('Mot de passe réinitialisé', [
                'user_id' => $user->getId(),
                'email' => $email,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la réinitialisation', [
                'token' => substr($request->token, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la réinitialisation',
            ], 500);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Déconnexion utilisateur', [
            'user_id' => $user->getId(),
            'ip' => $request->getClientIp(),
        ]);

        // Créer la réponse de succès
        $response = $this->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);

        // Effacer le cookie BEARER
        $response->headers->setCookie(
            Cookie::create('BEARER')
                ->withValue('')
                ->withExpires(time() - 3600) // Expire dans le passé
                ->withPath('/')
                ->withSecure(false) // true en production avec HTTPS
                ->withHttpOnly(true)
                ->withSameSite('lax'),
        );

        return $response;
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function refresh(#[CurrentUser] User $user): JsonResponse
    {
        try {
            // Créer un nouveau token
            $token = $this->jwtManager->create($user);

            // Créer la réponse avec les données utilisateur mises à jour
            $response = $this->json([
                'success' => true,
                'message' => 'Token rafraîchi avec succès',
                'data' => [
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName(),
                        'roles' => $user->getRoles(),
                        'isVerified' => $user->isVerified(),
                    ],
                ],
            ]);

            // Définir le nouveau token dans les cookies HttpOnly
            $response->headers->setCookie(
                Cookie::create('BEARER')
                    ->withValue($token)
                    ->withExpires(time() + 3600) // 1 heure
                    ->withPath('/')
                    ->withSecure(false) // true en production avec HTTPS
                    ->withHttpOnly(true)
                    ->withSameSite('lax'),
            );

            $this->logger->info('Token rafraîchi', [
                'user_id' => $user->getId(),
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors du rafraîchissement du token', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Impossible de rafraîchir le token',
            ], 500);
        }
    }

    #[Route('/check', name: 'check', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function check(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'success' => true,
            'authenticated' => true,
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
            ],
        ]);
    }

    /** @throws InvalidArgumentException */
    #[Route('/verify-reset-token/{token}', name: 'verify_reset_token', methods: ['GET'])]
    public function verifyResetToken(string $token): JsonResponse
    {
        $tokenKey = 'reset_token_' . $token;
        $tokenItem = $this->cache->getItem($tokenKey);

        if (!$tokenItem->isHit()) {
            return $this->json([
                'success' => false,
                'message' => 'Token invalide ou expiré',
            ], 400);
        }

        return $this->json([
            'success' => true,
            'message' => 'Token valide',
            'data' => [
                'email' => $tokenItem->get(),
            ],
        ]);
    }
}
