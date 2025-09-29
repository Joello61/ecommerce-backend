<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\EventListener\OrderCreatedEvent;
use App\EventListener\OrderStatusChangedEvent;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function count;
use function in_array;
use function sprintf;

readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private AddressRepository $addressRepository,
        private CartService $cartService,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createOrderFromCart(User $user, int $shippingAddressId, int $billingAddressId, ?string $notes = null): Order
    {
        $cart = $this->cartService->getOrCreateCart($user);
        $this->validateCartForOrder($cart);

        $shippingAddress = $this->getValidAddress($user, $shippingAddressId);
        $billingAddress = $this->getValidAddress($user, $billingAddressId);

        $order = $this->createOrder($user, $shippingAddress, $billingAddress, $notes);
        $this->processCartItems($cart, $order);

        $this->entityManager->persist($order);
        $this->cartService->clearCart($user);
        $this->entityManager->flush();

        // Dispatcher l'événement de création de commande
        $this->eventDispatcher->dispatch(new OrderCreatedEvent($order));

        return $order;
    }

    public function updateOrderStatus(int $orderId, string $status): Order
    {
        $order = $this->getOrder($orderId);
        $this->validateStatusTransition($status);

        $oldStatus = $order->getStatus();
        $order->setStatus($status);

        $this->handleStatusSpecificActions($order, $status, $oldStatus);
        $this->entityManager->flush();

        // Dispatcher l'événement de changement de statut
        $this->eventDispatcher->dispatch(new OrderStatusChangedEvent($order, $oldStatus, $status));

        return $order;
    }

    public function cancelOrder(int $orderId): Order
    {
        $order = $this->getOrder($orderId);

        if (!$order->canBeCancelled()) {
            throw new BadRequestHttpException('Cette commande ne peut plus être annulée');
        }

        return $this->updateOrderStatus($orderId, Order::STATUS_CANCELLED);
    }

    public function getOrdersByUser(User $user): array
    {
        return $this->orderRepository->findByUser($user);
    }

    public function getOrderByNumberAndUser(string $orderNumber, User $user): ?Order
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);

        if (!$order || $order->getUser() !== $user) {
            return null;
        }

        return $order;
    }

    public function getOrderSummary(Order $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'productName' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
                'totalPrice' => $item->getTotalPrice(),
            ];
        }

        return [
            'order' => [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'totalPrice' => $order->getTotalPrice(),
                'createdAt' => $order->getCreatedAt(),
                'shippedAt' => $order->getShippedAt(),
                'deliveredAt' => $order->getDeliveredAt(),
                'totalItems' => $order->getTotalItems(),
                'notes' => $order->getNotes(),
            ],
            'items' => $items,
            'addresses' => [
                'shipping' => [
                    'formatted' => $order->getShippingAddress()->getFormattedAddress(),
                ],
                'billing' => [
                    'formatted' => $order->getBillingAddress()->getFormattedAddress(),
                ],
            ],
        ];
    }

    public function getRevenueStats(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $revenue = $this->orderRepository->getRevenueByPeriod($start, $end);
        $orders = $this->orderRepository->findOrdersInPeriod($start, $end);

        return [
            'totalRevenue' => $revenue,
            'totalOrders' => count($orders),
            'averageOrderValue' => count($orders) > 0 ? $revenue / count($orders) : 0,
        ];
    }

    public function getOrdersRequiringAttention(): array
    {
        return $this->orderRepository->findOrdersRequiringAttention();
    }

    public function getPendingOrders(): array
    {
        return $this->orderRepository->findPendingOrders();
    }

    public function getOrdersToShip(): array
    {
        return $this->orderRepository->findOrdersToShip();
    }

    // Méthodes privées pour la logique interne
    private function validateCartForOrder(Cart $cart): void
    {
        if ($cart->isEmpty()) {
            throw new BadRequestHttpException('Le panier est vide');
        }

        $stockErrors = $this->cartService->validateCartStock($cart);
        if (!empty($stockErrors)) {
            throw new BadRequestHttpException('Erreurs de stock: ' . implode(', ', $stockErrors));
        }
    }

    private function getValidAddress(User $user, int $addressId): Address
    {
        $address = $this->addressRepository->find($addressId);

        if (!$address || $address->getUser() !== $user) {
            throw new NotFoundHttpException('Adresse non trouvée');
        }

        return $address;
    }

    private function createOrder(User $user, Address $shippingAddress, Address $billingAddress, ?string $notes): Order
    {
        $order = new Order();
        $order->setUser($user)
            ->setShippingAddress($shippingAddress)
            ->setBillingAddress($billingAddress)
            ->setNotes($notes);

        return $order;
    }

    private function processCartItems(Cart $cart, Order $order): void
    {
        $totalPrice = 0;

        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();

            // Vérification finale du stock
            if ($cartItem->getQuantity() > $product->getStock()) {
                throw new BadRequestHttpException(
                    sprintf('Stock insuffisant pour %s', $product->getName()),
                );
            }

            $orderItem = $this->createOrderItem($order, $cartItem);
            $order->addItem($orderItem);

            // Décrémenter le stock
            $product->setStock($product->getStock() - $cartItem->getQuantity());
            $this->entityManager->persist($product);

            $totalPrice += (float) $orderItem->getTotalPrice();
        }

        $order->setTotalPrice((string) $totalPrice);
    }

    private function createOrderItem(Order $order, $cartItem): OrderItem
    {
        $product = $cartItem->getProduct();

        $orderItem = new OrderItem();
        $orderItem->setOrderRef($order)
            ->setProduct($product)
            ->setQuantity($cartItem->getQuantity())
            ->setPrice($product->getPrice())
            ->setProductName($product->getName());

        return $orderItem;
    }

    private function getOrder(int $orderId): Order
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            throw new NotFoundHttpException('Commande non trouvée');
        }

        return $order;
    }

    private function validateStatusTransition(string $status): void
    {
        $validStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        if (!in_array($status, $validStatuses, true)) {
            throw new BadRequestHttpException('Statut invalide');
        }
    }

    private function handleStatusSpecificActions(Order $order, string $status, string $oldStatus): void
    {
        switch ($status) {
            case Order::STATUS_SHIPPED:
                if (!$order->getShippedAt()) {
                    $order->setShippedAt(new DateTimeImmutable());
                }

                break;
            case Order::STATUS_DELIVERED:
                if (!$order->getDeliveredAt()) {
                    $order->setDeliveredAt(new DateTimeImmutable());
                }

                break;
            case Order::STATUS_CANCELLED:
                if ($oldStatus !== Order::STATUS_CANCELLED) {
                    $this->restoreStockFromOrder($order);
                }

                break;
        }
    }

    private function restoreStockFromOrder(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product) {
                $product->setStock($product->getStock() + $item->getQuantity());
                $this->entityManager->persist($product);
            }
        }
    }
}
