<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Cart\AddToCartRequest;
use App\DTO\Cart\MergeGuestCartRequest;
use App\DTO\Cart\UpdateCartItemRequest;
use App\Entity\User;
use App\Service\CartService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function count;

#[Route('/api/cart', name: 'api_cart_')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'show', methods: ['GET'])]
    public function getCart(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $cartSummary = $this->cartService->getCartSummary($user);

            return $this->json([
                'success' => true,
                'data' => $cartSummary,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération panier', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du panier',
            ], 500);
        }
    }

    #[Route('/add', name: 'add_item', methods: ['POST'])]
    public function addToCart(
        #[MapRequestPayload]
        AddToCartRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $cartItem = $this->cartService->addToCart(
                $user,
                $request->productId,
                $request->quantity,
            );

            $this->logger->info('Produit ajouté au panier', [
                'user_id' => $user->getId(),
                'product_id' => $request->productId,
                'quantity' => $request->quantity,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Produit ajouté au panier avec succès',
                'data' => [
                    'cartItem' => [
                        'id' => $cartItem->getId(),
                        'quantity' => $cartItem->getQuantity(),
                        'totalPrice' => $cartItem->getTotalPrice(),
                        'product' => [
                            'id' => $cartItem->getProduct()->getId(),
                            'name' => $cartItem->getProduct()->getName(),
                            'price' => $cartItem->getProduct()->getPrice(),
                        ],
                    ],
                    'cartSummary' => $this->cartService->getCartSummary($user)['cart'],
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
            $this->logger->error('Erreur ajout au panier', [
                'user_id' => $user->getId(),
                'product_id' => $request->productId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'ajout au panier',
            ], 500);
        }
    }

    #[Route('/items/{id}', name: 'update_item', methods: ['PUT', 'PATCH'])]
    public function updateCartItem(
        int $id,
        #[MapRequestPayload]
        UpdateCartItemRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $cartItem = $this->cartService->updateCartItem($user, $id, $request->quantity);

            $this->logger->info('Article du panier mis à jour', [
                'user_id' => $user->getId(),
                'cart_item_id' => $id,
                'new_quantity' => $request->quantity,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Quantité mise à jour avec succès',
                'data' => [
                    'cartItem' => [
                        'id' => $cartItem->getId(),
                        'quantity' => $cartItem->getQuantity(),
                        'totalPrice' => $cartItem->getTotalPrice(),
                        'updatedAt' => $cartItem->getUpdatedAt(),
                    ],
                    'cartSummary' => $this->cartService->getCartSummary($user)['cart'],
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
            $this->logger->error('Erreur mise à jour article panier', [
                'user_id' => $user->getId(),
                'cart_item_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour',
            ], 500);
        }
    }

    #[Route('/items/{id}', name: 'remove_item', methods: ['DELETE'])]
    public function removeFromCart(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $this->cartService->removeFromCart($user, $id);

            $this->logger->info('Article supprimé du panier', [
                'user_id' => $user->getId(),
                'cart_item_id' => $id,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Article supprimé du panier avec succès',
                'data' => [
                    'cartSummary' => $this->cartService->getCartSummary($user)['cart'],
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur suppression article panier', [
                'user_id' => $user->getId(),
                'cart_item_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression',
            ], 500);
        }
    }

    #[Route('/clear', name: 'clear', methods: ['DELETE'])]
    public function clearCart(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $this->cartService->clearCart($user);

            $this->logger->info('Panier vidé', [
                'user_id' => $user->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Panier vidé avec succès',
                'data' => [
                    'cartSummary' => $this->cartService->getCartSummary($user)['cart'],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur vidage panier', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du vidage du panier',
            ], 500);
        }
    }

    #[Route('/merge', name: 'merge_guest', methods: ['POST'])]
    public function mergeGuestCart(
        #[MapRequestPayload]
        MergeGuestCartRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        try {
            $cart = $this->cartService->mergeGuestCart($user, $request->items);

            $this->logger->info('Panier invité fusionné', [
                'user_id' => $user->getId(),
                'guest_items_count' => count($request->items),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Panier fusionné avec succès',
                'data' => [
                    'cartSummary' => $this->cartService->getCartSummary($user),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur fusion panier invité', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la fusion du panier',
            ], 500);
        }
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validateCart(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $cart = $this->cartService->getOrCreateCart($user);
            $errors = $this->cartService->validateCartStock($cart);

            if (empty($errors)) {
                return $this->json([
                    'success' => true,
                    'message' => 'Panier valide',
                    'data' => [
                        'isValid' => true,
                        'cartSummary' => $this->cartService->getCartSummary($user)['cart'],
                    ],
                ]);
            }

            return $this->json([
                'success' => false,
                'message' => 'Des problèmes ont été détectés dans votre panier',
                'data' => [
                    'isValid' => false,
                    'errors' => $errors,
                    'cartSummary' => $this->cartService->getCartSummary($user)['cart'],
                ],
            ], 400);
        } catch (Exception $e) {
            $this->logger->error('Erreur validation panier', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la validation',
            ], 500);
        }
    }

    #[Route('/count', name: 'count', methods: ['GET'])]
    public function getCartCount(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $cartSummary = $this->cartService->getCartSummary($user);

            return $this->json([
                'success' => true,
                'data' => [
                    'totalItems' => $cartSummary['cart']['totalItems'],
                    'totalQuantity' => $cartSummary['cart']['totalQuantity'],
                    'isEmpty' => $cartSummary['cart']['isEmpty'],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur comptage panier', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/quick-add/{productId}/{quantity}', name: 'quick_add', methods: ['POST'])]
    public function quickAddToCart(int $productId, int $quantity, #[CurrentUser] User $user): JsonResponse
    {
        // Validation simple des paramètres URL
        if ($quantity <= 0 || $quantity > 99) {
            return $this->json([
                'success' => false,
                'message' => 'Quantité invalide',
            ], 400);
        }

        try {
            $cartItem = $this->cartService->addToCart($user, $productId, $quantity);

            return $this->json([
                'success' => true,
                'message' => 'Produit ajouté rapidement',
                'data' => [
                    'cartCount' => $this->cartService->getCartSummary($user)['cart']['totalQuantity'],
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
                'message' => 'Produit non trouvé',
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur ajout rapide au panier', [
                'user_id' => $user->getId(),
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }
}
