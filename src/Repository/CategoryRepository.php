<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function save(Category $category, bool $flush = false): void
    {
        $this->getEntityManager()->persist($category);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Category $category, bool $flush = false): void
    {
        $this->getEntityManager()->remove($category);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findCategoriesWithProducts(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.products', 'p')
            ->where('c.isActive = :active')
            ->andWhere('p.isActive = :productActive')
            ->setParameter('active', true)
            ->setParameter('productActive', true)
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCategoriesWithProductCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(p.id) as productCount')
            ->leftJoin('c.products', 'p', 'WITH', 'p.isActive = :productActive')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('productActive', true)
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchCategories(string $search): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('c.name LIKE :search OR c.description LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPopularCategories(int $limit = 6): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(p.id) as productCount')
            ->leftJoin('c.products', 'p', 'WITH', 'p.isActive = :productActive')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('productActive', true)
            ->groupBy('c.id')
            ->having('COUNT(p.id) > 0')
            ->orderBy('productCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
