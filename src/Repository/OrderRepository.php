<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $order, bool $flush = false): void
    {
        $this->getEntityManager()->persist($order);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $order, bool $flush = false): void
    {
        $this->getEntityManager()->remove($order);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->findOneBy(['orderNumber' => $orderNumber]);
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentOrders(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOrdersInPeriod(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingOrders(): array
    {
        return $this->findByStatus(Order::STATUS_PENDING);
    }

    public function findProcessingOrders(): array
    {
        return $this->findByStatus(Order::STATUS_PROCESSING);
    }

    public function findCompletedOrders(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', [Order::STATUS_DELIVERED])
            ->orderBy('o.deliveredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCancelledOrders(): array
    {
        return $this->findByStatus(Order::STATUS_CANCELLED);
    }

    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(CAST(o.totalPrice AS DECIMAL(10,2)))')
            ->where('o.status = :status')
            ->setParameter('status', Order::STATUS_DELIVERED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getRevenueByPeriod(DateTimeImmutable $start, DateTimeImmutable $end): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(CAST(o.totalPrice AS DECIMAL(10,2)))')
            ->where('o.status = :status')
            ->andWhere('o.createdAt BETWEEN :start AND :end')
            ->setParameter('status', Order::STATUS_DELIVERED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function countOrdersByStatus(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.status', 'COUNT(o.id) as count')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();
    }

    public function findTopCustomers(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->select('u', 'COUNT(o.id) as orderCount', 'SUM(CAST(o.totalPrice AS DECIMAL(10,2))) as totalSpent')
            ->join('o.user', 'u')
            ->where('o.status = :status')
            ->setParameter('status', Order::STATUS_DELIVERED)
            ->groupBy('u.id')
            ->orderBy('totalSpent', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOrdersToShip(): array
    {
        return $this->findByStatus(Order::STATUS_CONFIRMED);
    }

    public function findOrdersRequiringAttention(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :pending AND o.createdAt < :threshold')
            ->orWhere('o.status = :processing AND o.updatedAt < :threshold')
            ->setParameter('pending', Order::STATUS_PENDING)
            ->setParameter('processing', Order::STATUS_PROCESSING)
            ->setParameter('threshold', new DateTimeImmutable('-2 hours'))
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
