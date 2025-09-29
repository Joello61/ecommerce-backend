<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use App\Service\EmailService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderCreatedEvent::class)]
class OrderCreatedListener
{
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(OrderCreatedEvent $event): void
    {
        $order = $event->getOrder();

        // Envoyer l'email de confirmation de commande
        $this->sendOrderConfirmation($order);

        // Notifier l'administration d'une nouvelle commande
        $this->notifyAdministration($order);

        // Log de la création de commande pour analytics
        $this->logOrderCreation($order);
    }

    private function sendOrderConfirmation(Order $order): void
    {
        try {
            $this->emailService->sendOrderConfirmation($order);
            $this->logger->info('Email de confirmation envoyé', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'user_email' => $order->getUser()->getEmail(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi confirmation commande', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyAdministration(Order $order): void
    {
        try {
            // Email à l'administration pour nouvelle commande
            $this->emailService->sendAdminOrderNotification($order);

            $this->logger->info('Notification admin envoyée', [
                'order_id' => $order->getId(),
                'total' => $order->getTotalPrice(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur notification admin', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logOrderCreation(Order $order): void
    {
        $this->logger->info('Nouvelle commande créée', [
            'order_id' => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'user_id' => $order->getUser()->getId(),
            'total_price' => $order->getTotalPrice(),
            'items_count' => $order->getTotalItems(),
            'status' => $order->getStatus(),
            'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
