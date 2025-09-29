<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function save(CartItem $cartItem, bool $flush = false): void
    {
        $this->getEntityManager()->persist($cartItem);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CartItem $cartItem, bool $flush = false): void
    {
        $this->getEntityManager()->remove($cartItem);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCartAndProduct(Cart $cart, Product $product): ?CartItem
    {
        return $this->findOneBy(['cart' => $cart, 'product' => $product]);
    }

    public function findByCart(Cart $cart): array
    {
        return $this->createQueryBuilder('ci')
            ->join('ci.product', 'p')
            ->where('ci.cart = :cart')
            ->andWhere('p.isActive = :active')
            ->setParameter('cart', $cart)
            ->setParameter('active', true)
            ->orderBy('ci.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCart(Cart $cart): int
    {
        return (int) $this->createQueryBuilder('ci')
            ->select('COUNT(ci.id)')
            ->where('ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalQuantityByCart(Cart $cart): int
    {
        $result = $this->createQueryBuilder('ci')
            ->select('SUM(ci.quantity)')
            ->where('ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function findPopularProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('ci')
            ->select('p', 'SUM(ci.quantity) as totalQuantity')
            ->join('ci.product', 'p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.id')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function removeOldCartItems(DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('ci')
            ->delete()
            ->where('ci.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
