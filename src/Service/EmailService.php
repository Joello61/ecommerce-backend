<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

use function count;
use function sprintf;

readonly class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $fromEmail = 'no-reply@ecommerce.com',
        private string $fromName = 'E-commerce',
        private string $adminEmail = 'admin@ecommerce.com',
    ) {}

    public function sendWelcomeEmail(User $user): bool
    {
        return $this->sendTemplatedEmail(
            $user->getEmail(),
            'Bienvenue sur notre boutique !',
            'emails/welcome.html.twig',
            ['user' => $user],
            'Email de bienvenue envoyé',
            ['user_id' => $user->getId()],
        );
    }

    public function sendOrderConfirmation(Order $order): bool
    {
        return $this->sendTemplatedEmail(
            $order->getUser()->getEmail(),
            sprintf('Confirmation de commande %s', $order->getOrderNumber()),
            'emails/order_confirmation.html.twig',
            ['order' => $order, 'user' => $order->getUser()],
            'Email de confirmation de commande envoyé',
            [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
            ],
        );
    }

    public function sendOrderStatusUpdate(Order $order): bool
    {
        $statusLabels = $this->getStatusLabels();
        $statusLabel = $statusLabels[$order->getStatus()] ?? $order->getStatus();

        return $this->sendTemplatedEmail(
            $order->getUser()->getEmail(),
            sprintf('Mise à jour de votre commande %s - %s', $order->getOrderNumber(), $statusLabel),
            'emails/order_status_update.html.twig',
            [
                'order' => $order,
                'user' => $order->getUser(),
                'statusLabel' => $statusLabel,
            ],
            'Email de mise à jour de statut envoyé',
            [
                'order_id' => $order->getId(),
                'status' => $order->getStatus(),
            ],
        );
    }

    public function sendAdminOrderNotification(Order $order): bool
    {
        return $this->sendTemplatedEmail(
            $this->adminEmail,
            sprintf('Nouvelle commande %s - %s€', $order->getOrderNumber(), $order->getTotalPrice()),
            'emails/admin_new_order.html.twig',
            ['order' => $order],
            'Notification admin nouvelle commande envoyée',
            [
                'order_id' => $order->getId(),
                'total' => $order->getTotalPrice(),
            ],
        );
    }

    public function sendPasswordResetEmail(User $user, string $resetToken): bool
    {
        return $this->sendTemplatedEmail(
            $user->getEmail(),
            'Réinitialisation de votre mot de passe',
            'emails/password_reset.html.twig',
            [
                'user' => $user,
                'resetToken' => $resetToken,
                'expiresAt' => (new DateTimeImmutable())->add(new DateInterval('PT1H')),
            ],
            'Email de réinitialisation envoyé',
            ['user_id' => $user->getId()],
        );
    }

    public function sendContactFormEmail(array $contactData): bool
    {
        return $this->sendTemplatedEmail(
            $this->adminEmail,
            sprintf('Nouveau message de contact - %s', $contactData['subject'] ?? 'Sans sujet'),
            'emails/contact_form.html.twig',
            ['contactData' => $contactData],
            'Email de contact envoyé',
            ['from' => $contactData['email']],
            $contactData['email'], // replyTo
        );
    }

    public function sendLowStockAlert(array $products): bool
    {
        if (empty($products)) {
            return true;
        }

        return $this->sendTemplatedEmail(
            $this->adminEmail,
            'Alerte - Produits en stock faible',
            'emails/low_stock_alert.html.twig',
            ['products' => $products],
            'Alerte stock envoyée',
            ['products_count' => count($products)],
        );
    }

    public function sendAbandonedCartReminder(User $user, array $cartItems): bool
    {
        return $this->sendTemplatedEmail(
            $user->getEmail(),
            'Vous avez oublié quelque chose dans votre panier',
            'emails/abandoned_cart.html.twig',
            ['user' => $user, 'cartItems' => $cartItems],
            'Rappel panier abandonné envoyé',
            ['user_id' => $user->getId()],
        );
    }

    public function sendNewsletterEmail(array $recipients, string $subject, string $content): bool
    {
        $results = ['sent' => 0, 'failed' => 0];

        foreach ($recipients as $recipient) {
            $success = $this->sendTemplatedEmail(
                $recipient['email'],
                $subject,
                'emails/newsletter.html.twig',
                [
                    'content' => $content,
                    'recipient' => $recipient,
                    'unsubscribeUrl' => sprintf('/newsletter/unsubscribe/%s', $recipient['token'] ?? ''),
                ],
                null, // Pas de log individuel pour la newsletter
                null,
                null,
                false, // Pas de log d'erreur pour chaque email
            );

            $success ? $results['sent']++ : $results['failed']++;
        }

        $this->logger->info('Newsletter envoyée', $results);

        return $results['failed'] === 0;
    }

    public function sendBulkEmail(array $recipients, string $subject, string $template, array $context = []): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recipients as $recipient) {
            try {
                $email = $this->createTemplatedEmail(
                    $recipient['email'],
                    $subject,
                    $template,
                    array_merge($context, ['recipient' => $recipient]),
                );

                $this->mailer->send($email);
                ++$results['sent'];
            } catch (Exception $e) {
                ++$results['failed'];
                $results['errors'][] = [
                    'email' => $recipient['email'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->logger->info('Envoi en masse terminé', $results);

        return $results;
    }

    // Méthodes privées pour la logique interne
    private function sendTemplatedEmail(
        string $to,
        string $subject,
        string $template,
        array $context,
        ?string $successMessage = null,
        ?array $successContext = null,
        ?string $replyTo = null,
        bool $logErrors = true,
    ): bool {
        try {
            $email = $this->createTemplatedEmail($to, $subject, $template, $context, $replyTo);
            $this->mailer->send($email);

            if ($successMessage) {
                $this->logger->info($successMessage, $successContext ?? []);
            }

            return true;
        } catch (Exception $e) {
            if ($logErrors) {
                $this->logger->error('Erreur envoi email', [
                    'to' => $to,
                    'subject' => $subject,
                    'template' => $template,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    private function createTemplatedEmail(
        string $to,
        string $subject,
        string $template,
        array $context,
        ?string $replyTo = null,
    ): TemplatedEmail {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        if ($replyTo) {
            $email->replyTo($replyTo);
        }

        return $email;
    }

    private function getStatusLabels(): array
    {
        return [
            Order::STATUS_PENDING => 'En attente',
            Order::STATUS_CONFIRMED => 'Confirmée',
            Order::STATUS_PROCESSING => 'En préparation',
            Order::STATUS_SHIPPED => 'Expédiée',
            Order::STATUS_DELIVERED => 'Livrée',
            Order::STATUS_CANCELLED => 'Annulée',
        ];
    }
}
