<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use App\Service\EmailService;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderStatusChangedEvent::class)]
class OrderStatusChangedListener
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(OrderStatusChangedEvent $event): void
    {
        $order = $event->getOrder();
        $newStatus = $event->getNewStatus();

        // Envoyer un email de mise à jour de statut
        $this->sendStatusUpdateEmail($order);

        // Actions spécifiques selon le nouveau statut
        match ($newStatus) {
            Order::STATUS_SHIPPED => $this->handleOrderShipped($order),
            Order::STATUS_DELIVERED => $this->handleOrderDelivered($order),
            Order::STATUS_CANCELLED => $this->handleOrderCancelled($order),
            default => null,
        };

        $this->logStatusChange($event);
    }

    private function sendStatusUpdateEmail(Order $order): void
    {
        try {
            $this->emailService->sendOrderStatusUpdate($order);
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email statut', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleOrderShipped(Order $order): void
    {
        $this->logger->info('Commande expédiée', [
            'order_id' => $order->getId(),
            'shipped_at' => $order->getShippedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function handleOrderDelivered(Order $order): void
    {
        $this->logger->info('Commande livrée', [
            'order_id' => $order->getId(),
            'delivered_at' => $order->getDeliveredAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function handleOrderCancelled(Order $order): void
    {
        $this->logger->info('Commande annulée', [
            'order_id' => $order->getId(),
            'total_refunded' => $order->getTotalPrice(),
        ]);
    }

    private function logStatusChange(OrderStatusChangedEvent $event): void
    {
        $this->logger->info('Changement statut commande', [
            'order_id' => $event->getOrder()->getId(),
            'old_status' => $event->getOldStatus(),
            'new_status' => $event->getNewStatus(),
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
