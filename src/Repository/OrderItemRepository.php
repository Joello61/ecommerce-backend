<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    public function save(OrderItem $orderItem, bool $flush = false): void
    {
        $this->getEntityManager()->persist($orderItem);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderItem $orderItem, bool $flush = false): void
    {
        $this->getEntityManager()->remove($orderItem);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.orderRef = :order')
            ->setParameter('order', $order)
            ->orderBy('oi.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBestSellingProducts(int $limit = 10, ?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('p', 'SUM(oi.quantity) as totalSold', 'SUM(CAST(oi.price AS DECIMAL(10,2)) * oi.quantity) as totalRevenue')
            ->join('oi.product', 'p')
            ->join('oi.orderRef', 'o')
            ->where('o.status = :delivered')
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->groupBy('p.id')
            ->orderBy('totalSold', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('o.createdAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function findProductSalesStats(Product $product): array
    {
        return $this->createQueryBuilder('oi')
            ->select('oi', 'o', 'COUNT(oi.id) as orderCount', 'SUM(oi.quantity) as totalSold', 'AVG(CAST(oi.price AS DECIMAL(10,2))) as avgPrice')
            ->join('oi.orderRef', 'o')
            ->where('oi.product = :product')
            ->andWhere('o.status = :delivered')
            ->setParameter('product', $product)
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->groupBy('oi.product')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRevenueByProduct(?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('p.name as productName', 'SUM(CAST(oi.price AS DECIMAL(10,2)) * oi.quantity) as revenue', 'SUM(oi.quantity) as quantitySold')
            ->join('oi.product', 'p')
            ->join('oi.orderRef', 'o')
            ->where('o.status = :delivered')
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->groupBy('p.id')
            ->orderBy('revenue', 'DESC');

        if ($start && $end) {
            $qb->andWhere('o.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        return $qb->getQuery()->getResult();
    }

    public function findMostFrequentlyOrderedTogether(Product $product, int $limit = 5): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT p2, COUNT(oi2.id) as frequency
                FROM App\Entity\OrderItem oi1
                JOIN oi1.orderRef o1
                JOIN App\Entity\OrderItem oi2 WITH oi2.orderRef = o1
                JOIN oi2.product p2
                WHERE oi1.product = :product
                AND oi2.product != :product
                AND o1.status = :delivered
                GROUP BY p2.id
                ORDER BY frequency DESC
            ')
            ->setParameter('product', $product)
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->setMaxResults($limit)
            ->getResult();
    }

    public function getOrderItemsByDateRange(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('oi')
            ->join('oi.orderRef', 'o')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->andWhere('o.status = :delivered')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLowPerformingProducts(int $threshold = 5, ?DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('p', 'SUM(oi.quantity) as totalSold')
            ->join('oi.product', 'p')
            ->join('oi.orderRef', 'o')
            ->where('o.status = :delivered')
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->groupBy('p.id')
            ->having('totalSold <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('totalSold', 'ASC');

        if ($since) {
            $qb->andWhere('o.createdAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function calculateAverageOrderValue(): float
    {
        $result = $this->createQueryBuilder('oi')
            ->select('AVG(CAST(oi.price AS DECIMAL(10,2)) * oi.quantity)')
            ->join('oi.orderRef', 'o')
            ->where('o.status = :delivered')
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}
