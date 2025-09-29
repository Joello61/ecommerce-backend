<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Cart;
use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UserRegisteredEvent::class)]
class UserRegisteredListener
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(UserRegisteredEvent $event): void
    {
        $user = $event->getUser();

        // Créer un panier pour le nouvel utilisateur
        $this->createUserCart($user);

        // Envoyer l'email de bienvenue
        $this->sendWelcomeEmail($user);
    }

    private function createUserCart(User $user): void
    {
        // Vérifier qu'il n'a pas déjà un panier
        if ($user->getCart() === null) {
            $cart = new Cart();
            $cart->setUser($user);
            $user->setCart($cart);

            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }
    }

    private function sendWelcomeEmail(User $user): void
    {
        // Envoi asynchrone pour ne pas bloquer l'inscription
        try {
            $this->emailService->sendWelcomeEmail($user);
        } catch (Exception $e) {
            // Log l'erreur mais ne fait pas échouer l'inscription
            error_log('Erreur envoi email bienvenue: ' . $e->getMessage());
        }
    }
}
