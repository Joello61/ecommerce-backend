<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use App\Service\EmailService;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ProductStockLowEvent::class)]
readonly class ProductStockLowListener
{
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProductStockLowEvent $event): void
    {
        $product = $event->getProduct();

        // Envoyer une alerte email
        $this->sendLowStockAlert($product, $event->getCurrentStock());

        // Logger l'alerte
        $this->logLowStockAlert($event);

        // Marquer le produit pour réapprovisionnement
        $this->markForRestock($product);
    }

    private function sendLowStockAlert(Product $product, int $currentStock): void
    {
        try {
            $this->emailService->sendLowStockAlert([
                [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'current_stock' => $currentStock,
                    'category' => $product->getCategory()->getName(),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi alerte stock', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logLowStockAlert(ProductStockLowEvent $event): void
    {
        $this->logger->warning('Stock faible détecté', [
            'product_id' => $event->getProduct()->getId(),
            'product_name' => $event->getProduct()->getName(),
            'current_stock' => $event->getCurrentStock(),
            'threshold' => $event->getThreshold(),
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function markForRestock(Product $product): void
    {
        // Ici on pourrait ajouter une logique pour marquer le produit
        // dans un système de gestion des stocks ou créer une tâche
        $this->logger->info('Produit marqué pour réapprovisionnement', [
            'product_id' => $product->getId(),
            'product_name' => $product->getName(),
        ]);
    }
}
