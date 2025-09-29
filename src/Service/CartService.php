<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function count;
use function sprintf;

readonly class CartService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
        private ProductRepository $productRepository,
    ) {}

    public function getOrCreateCart(User $user): Cart
    {
        return $this->cartRepository->findOrCreateByUser($user);
    }

    public function addToCart(User $user, int $productId, int $quantity): CartItem
    {
        $this->validateQuantity($quantity);
        $product = $this->getValidProduct($productId);
        $this->validateStock($product, $quantity);

        $cart = $this->getOrCreateCart($user);
        $existingItem = $this->cartItemRepository->findByCartAndProduct($cart, $product);

        if ($existingItem) {
            return $this->updateExistingItem($existingItem, $quantity, $product);
        }

        return $this->createNewCartItem($cart, $product, $quantity);
    }

    public function updateCartItem(User $user, int $cartItemId, int $quantity): CartItem
    {
        $this->validateQuantity($quantity);
        $cartItem = $this->getValidCartItem($user, $cartItemId);
        $this->validateStock($cartItem->getProduct(), $quantity);

        $cartItem->setQuantity($quantity);
        $this->updateCartTimestamp($cartItem->getCart());
        $this->entityManager->flush();

        return $cartItem;
    }

    public function removeFromCart(User $user, int $cartItemId): void
    {
        $cartItem = $this->getValidCartItem($user, $cartItemId);
        $cart = $cartItem->getCart();

        $cart->removeItem($cartItem);
        $this->updateCartTimestamp($cart);

        $this->entityManager->remove($cartItem);
        $this->entityManager->flush();
    }

    public function clearCart(User $user): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->clear();
        $this->updateCartTimestamp($cart);
        $this->entityManager->flush();
    }

    public function getCartSummary(User $user): array
    {
        $cart = $this->getOrCreateCart($user);
        $items = $this->cartItemRepository->findByCart($cart);

        return $this->buildCartSummary($cart, $items);
    }

    public function validateCartStock(Cart $cart): array
    {
        $errors = [];
        $items = $this->cartItemRepository->findByCart($cart);

        foreach ($items as $item) {
            $product = $item->getProduct();

            if (!$product->isActive()) {
                $errors[] = sprintf('Le produit "%s" n\'est plus disponible', $product->getName());

                continue;
            }

            if (!$product->isInStock()) {
                $errors[] = sprintf('Le produit "%s" est en rupture de stock', $product->getName());

                continue;
            }

            if ($item->getQuantity() > $product->getStock()) {
                $errors[] = sprintf(
                    'Stock insuffisant pour "%s" (demandé: %d, disponible: %d)',
                    $product->getName(),
                    $item->getQuantity(),
                    $product->getStock(),
                );
            }
        }

        return $errors;
    }

    public function mergeGuestCart(User $user, array $guestCartData): Cart
    {
        $cart = $this->getOrCreateCart($user);

        foreach ($guestCartData as $item) {
            try {
                $this->addToCart($user, $item['productId'], $item['quantity']);
            } catch (Exception) {
                // Continue avec les autres articles en cas d'erreur
                continue;
            }
        }

        return $cart;
    }

    public function getAbandonedCarts(DateTimeInterface $before): array
    {
        return $this->cartRepository->findAbandonedCarts($before);
    }

    public function cleanupAbandonedCarts(DateTimeInterface $before): int
    {
        $carts = $this->getAbandonedCarts($before);
        $count = 0;

        foreach ($carts as $cart) {
            $this->entityManager->remove($cart);
            ++$count;
        }

        $this->entityManager->flush();

        return $count;
    }

    // Méthodes privées pour la logique interne
    private function validateQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new BadRequestHttpException('La quantité doit être positive');
        }
    }

    private function getValidProduct(int $productId): Product
    {
        $product = $this->productRepository->find($productId);
        if (!$product || !$product->isActive()) {
            throw new NotFoundHttpException('Produit non trouvé ou indisponible');
        }

        return $product;
    }

    private function validateStock(Product $product, int $quantity): void
    {
        if (!$product->isInStock()) {
            throw new BadRequestHttpException('Produit en rupture de stock');
        }

        if ($quantity > $product->getStock()) {
            throw new BadRequestHttpException(
                sprintf('Quantité demandée (%d) supérieure au stock disponible (%d)', $quantity, $product->getStock()),
            );
        }
    }

    private function getValidCartItem(User $user, int $cartItemId): CartItem
    {
        $cartItem = $this->cartItemRepository->find($cartItemId);
        if (!$cartItem || $cartItem->getCart()->getUser() !== $user) {
            throw new NotFoundHttpException('Article du panier non trouvé');
        }

        return $cartItem;
    }

    private function updateExistingItem(CartItem $existingItem, int $quantity, Product $product): CartItem
    {
        $newQuantity = $existingItem->getQuantity() + $quantity;
        if ($newQuantity > $product->getStock()) {
            throw new BadRequestHttpException('Stock insuffisant pour cette quantité');
        }

        $existingItem->setQuantity($newQuantity);
        $this->updateCartTimestamp($existingItem->getCart());
        $this->entityManager->flush();

        return $existingItem;
    }

    private function createNewCartItem(Cart $cart, Product $product, int $quantity): CartItem
    {
        $cartItem = new CartItem();
        $cartItem->setCart($cart)
            ->setProduct($product)
            ->setQuantity($quantity);

        $cart->addItem($cartItem);
        $this->updateCartTimestamp($cart);

        $this->entityManager->persist($cartItem);
        $this->entityManager->flush();

        return $cartItem;
    }

    private function updateCartTimestamp(Cart $cart): void
    {
        $cart->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->persist($cart);
    }

    private function buildCartSummary(Cart $cart, array $items): array
    {
        $totalPrice = 0;
        $totalQuantity = 0;
        $itemsData = [];

        foreach ($items as $item) {
            $itemTotal = (float) $item->getTotalPrice();
            $totalPrice += $itemTotal;
            $totalQuantity += $item->getQuantity();

            $itemsData[] = [
                'id' => $item->getId(),
                'product' => [
                    'id' => $item->getProduct()->getId(),
                    'name' => $item->getProduct()->getName(),
                    'price' => $item->getProduct()->getPrice(),
                    'stock' => $item->getProduct()->getStock(),
                ],
                'quantity' => $item->getQuantity(),
                'totalPrice' => $item->getTotalPrice(),
            ];
        }

        return [
            'cart' => [
                'id' => $cart->getId(),
                'totalItems' => count($items),
                'totalQuantity' => $totalQuantity,
                'totalPrice' => (string) $totalPrice,
                'isEmpty' => empty($items),
            ],
            'items' => $itemsData,
        ];
    }
}
