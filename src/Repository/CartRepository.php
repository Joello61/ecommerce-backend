<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function save(Cart $cart, bool $flush = false): void
    {
        $this->getEntityManager()->persist($cart);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Cart $cart, bool $flush = false): void
    {
        $this->getEntityManager()->remove($cart);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOrCreateByUser(User $user): Cart
    {
        $cart = $this->findOneBy(['user' => $user]);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->save($cart, true);
        }

        return $cart;
    }

    public function findActiveCartsWithItems(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.items', 'ci')
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAbandonedCarts(DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.items', 'ci')
            ->where('c.updatedAt < :before')
            ->setParameter('before', $before)
            ->orderBy('c.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveCartsWithItems(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.items', 'ci')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findCartsWithTotalValue(): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT c, u,
                       SUM(ci.quantity * CAST(p.price AS DECIMAL(10,2))) as totalValue
                FROM App\Entity\Cart c
                JOIN c.user u
                LEFT JOIN c.items ci
                LEFT JOIN ci.product p
                GROUP BY c.id
                HAVING totalValue > 0
                ORDER BY totalValue DESC
            ')
            ->getResult();
    }
}
