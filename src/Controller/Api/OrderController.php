<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Order\CreateOrderRequest;
use App\DTO\Order\OrderFilterRequest;
use App\Entity\User;
use App\Service\OrderService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function count;
use function in_array;

#[Route('/api/orders', name: 'api_orders_')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function getOrders(
        #[MapQueryString]
        OrderFilterRequest $filters,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            // Forcer l'utilisateur actuel dans les filtres pour la sécurité
            $filters->userId = $user->getId();

            $orders = $this->orderService->getOrdersByUser($user);

            $ordersData = array_map(static function ($order) {
                return [
                    'id' => $order->getId(),
                    'orderNumber' => $order->getOrderNumber(),
                    'status' => $order->getStatus(),
                    'totalPrice' => $order->getTotalPrice(),
                    'totalItems' => $order->getTotalItems(),
                    'createdAt' => $order->getCreatedAt(),
                    'updatedAt' => $order->getUpdatedAt(),
                    'shippedAt' => $order->getShippedAt(),
                    'deliveredAt' => $order->getDeliveredAt(),
                ];
            }, $orders);

            return $this->json([
                'success' => true,
                'data' => [
                    'orders' => $ordersData,
                    'total' => count($ordersData),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération commandes', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des commandes',
            ], 500);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function createOrder(
        #[MapRequestPayload]
        CreateOrderRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $order = $this->orderService->createOrderFromCart(
                $user,
                $request->shippingAddressId,
                $request->billingAddressId,
                $request->notes,
            );

            $this->logger->info('Nouvelle commande créée', [
                'user_id' => $user->getId(),
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'total' => $order->getTotalPrice(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => [
                    'order' => [
                        'id' => $order->getId(),
                        'orderNumber' => $order->getOrderNumber(),
                        'status' => $order->getStatus(),
                        'totalPrice' => $order->getTotalPrice(),
                        'createdAt' => $order->getCreatedAt(),
                    ],
                ],
            ], 201);
        } catch (BadRequestHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur création commande', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la commande',
            ], 500);
        }
    }

    #[Route('/{orderNumber}', name: 'show', methods: ['GET'])]
    public function getOrder(string $orderNumber, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderByNumberAndUser($orderNumber, $user);

            if (!$order) {
                throw new NotFoundHttpException('Commande non trouvée');
            }

            $orderSummary = $this->orderService->getOrderSummary($order);

            return $this->json([
                'success' => true,
                'data' => $orderSummary,
            ]);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération commande', [
                'user_id' => $user->getId(),
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération de la commande',
            ], 500);
        }
    }

    #[Route('/{orderNumber}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancelOrder(string $orderNumber, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderByNumberAndUser($orderNumber, $user);

            if (!$order) {
                throw new NotFoundHttpException('Commande non trouvée');
            }

            $updatedOrder = $this->orderService->cancelOrder($order->getId());

            $this->logger->info('Commande annulée par le client', [
                'user_id' => $user->getId(),
                'order_id' => $order->getId(),
                'order_number' => $orderNumber,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Commande annulée avec succès',
                'data' => [
                    'order' => [
                        'id' => $updatedOrder->getId(),
                        'orderNumber' => $updatedOrder->getOrderNumber(),
                        'status' => $updatedOrder->getStatus(),
                        'updatedAt' => $updatedOrder->getUpdatedAt(),
                    ],
                ],
            ]);
        } catch (BadRequestHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur annulation commande', [
                'user_id' => $user->getId(),
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'annulation',
            ], 500);
        }
    }

    #[Route('/{orderNumber}/track', name: 'track', methods: ['GET'])]
    public function trackOrder(string $orderNumber, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderByNumberAndUser($orderNumber, $user);

            if (!$order) {
                throw new NotFoundHttpException('Commande non trouvée');
            }

            $trackingInfo = [
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'createdAt' => $order->getCreatedAt(),
                'updatedAt' => $order->getUpdatedAt(),
                'shippedAt' => $order->getShippedAt(),
                'deliveredAt' => $order->getDeliveredAt(),
                'timeline' => $this->buildOrderTimeline($order),
                'canBeCancelled' => $order->canBeCancelled(),
                'isCompleted' => $order->isCompleted(),
            ];

            return $this->json([
                'success' => true,
                'data' => [
                    'tracking' => $trackingInfo,
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur suivi commande', [
                'user_id' => $user->getId(),
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getOrderStats(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $orders = $this->orderService->getOrdersByUser($user);

            $stats = [
                'totalOrders' => count($orders),
                'pendingOrders' => 0,
                'completedOrders' => 0,
                'cancelledOrders' => 0,
                'totalSpent' => 0,
                'averageOrderValue' => 0,
            ];

            foreach ($orders as $order) {
                switch ($order->getStatus()) {
                    case 'pending':
                    case 'confirmed':
                    case 'processing':
                        $stats['pendingOrders']++;

                        break;
                    case 'delivered':
                        $stats['completedOrders']++;
                        $stats['totalSpent'] += (float) $order->getTotalPrice();

                        break;
                    case 'cancelled':
                        $stats['cancelledOrders']++;

                        break;
                }
            }

            if ($stats['completedOrders'] > 0) {
                $stats['averageOrderValue'] = $stats['totalSpent'] / $stats['completedOrders'];
            }

            $stats['totalSpent'] = number_format($stats['totalSpent'], 2);
            $stats['averageOrderValue'] = number_format($stats['averageOrderValue'], 2);

            return $this->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur statistiques commandes', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    private function buildOrderTimeline($order): array
    {
        $timeline = [
            [
                'status' => 'pending',
                'label' => 'Commande reçue',
                'date' => $order->getCreatedAt(),
                'completed' => true,
            ],
        ];

        if (in_array($order->getStatus(), ['confirmed', 'processing', 'shipped', 'delivered'], true)) {
            $timeline[] = [
                'status' => 'confirmed',
                'label' => 'Commande confirmée',
                'date' => $order->getUpdatedAt(),
                'completed' => true,
            ];
        }

        if (in_array($order->getStatus(), ['processing', 'shipped', 'delivered'], true)) {
            $timeline[] = [
                'status' => 'processing',
                'label' => 'En préparation',
                'date' => $order->getUpdatedAt(),
                'completed' => true,
            ];
        }

        if (in_array($order->getStatus(), ['shipped', 'delivered'], true)) {
            $timeline[] = [
                'status' => 'shipped',
                'label' => 'Expédiée',
                'date' => $order->getShippedAt(),
                'completed' => true,
            ];
        }

        if ($order->getStatus() === 'delivered') {
            $timeline[] = [
                'status' => 'delivered',
                'label' => 'Livrée',
                'date' => $order->getDeliveredAt(),
                'completed' => true,
            ];
        }

        if ($order->getStatus() === 'cancelled') {
            $timeline[] = [
                'status' => 'cancelled',
                'label' => 'Annulée',
                'date' => $order->getUpdatedAt(),
                'completed' => true,
            ];
        }

        return $timeline;
    }
}
