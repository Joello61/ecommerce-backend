<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\EventListener\ProductStockLowEvent;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

use function count;

readonly class ProductService
{
    private const STOCK_LOW_THRESHOLD = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private SluggerInterface $slugger,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createProduct(array $data): Product
    {
        $category = $this->getValidCategory($data['categoryId']);

        $product = new Product();
        $this->populateProductData($product, $data, $category);
        $product->setSlug($this->generateUniqueSlug($data['name']));

        $this->handleImageUpload($product, $data);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    public function updateProduct(int $productId, array $data): Product
    {
        $product = $this->getProduct($productId);
        $originalStock = $product->getStock();

        $this->updateProductFields($product, $data);

        $product->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        // Vérifier si le stock a changé et dispatch l'événement si nécessaire
        $this->checkStockChange($product, $originalStock);

        return $product;
    }

    public function deleteProduct(int $productId): void
    {
        $product = $this->getProduct($productId);

        if (!$product->getOrderItems()->isEmpty()) {
            // Désactiver plutôt que supprimer si le produit a été commandé
            $product->setActive(false);
            $this->entityManager->flush();
        } else {
            $this->entityManager->remove($product);
            $this->entityManager->flush();
        }
    }

    public function getProduct(int $productId): Product
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new NotFoundHttpException('Produit non trouvé');
        }

        return $product;
    }

    public function getActiveProducts(): array
    {
        return $this->productRepository->findActiveProducts();
    }

    public function getFeaturedProducts(int $limit = 8): array
    {
        return $this->productRepository->findFeaturedProducts($limit);
    }

    public function getProductsByCategory(int $categoryId): array
    {
        $category = $this->getValidCategory($categoryId);

        return $this->productRepository->findProductsByCategory($category);
    }

    public function searchProducts(string $query, ?int $categoryId = null): array
    {
        $category = null;
        if ($categoryId) {
            $category = $this->getValidCategory($categoryId);
        }

        return $this->productRepository->searchProducts($query, $category);
    }

    public function getProductsWithFilters(array $filters, int $page = 1, int $limit = 20): array
    {
        $queryBuilder = $this->productRepository->findProductsWithFilter($filters);

        $totalQuery = clone $queryBuilder;
        $total = (int) $totalQuery->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $products = $queryBuilder
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public function getSimilarProducts(int $productId, int $limit = 4): array
    {
        $product = $this->getProduct($productId);

        return $this->productRepository->findSimilarProducts($product, $limit);
    }

    public function getBestSellers(int $limit = 10): array
    {
        return $this->productRepository->findBestSellers($limit);
    }

    public function getLowStockProducts(int $threshold = self::STOCK_LOW_THRESHOLD): array
    {
        return $this->productRepository->findLowStockProducts($threshold);
    }

    public function updateStock(int $productId, int $quantity, string $operation = 'set'): Product
    {
        $product = $this->getProduct($productId);
        $originalStock = $product->getStock();

        $newStock = $this->calculateNewStock($product, $quantity, $operation);
        $product->setStock($newStock);
        $product->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        $this->checkStockChange($product, $originalStock);

        return $product;
    }

    public function toggleFeatured(int $productId): Product
    {
        $product = $this->getProduct($productId);
        $product->setFeatured(!$product->isFeatured());
        $product->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $product;
    }

    public function toggleActive(int $productId): Product
    {
        $product = $this->getProduct($productId);
        $product->setActive(!$product->isActive());
        $product->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $product;
    }

    public function getProductStats(): array
    {
        return [
            'totalProducts' => $this->productRepository->count([]),
            'activeProducts' => $this->productRepository->count(['isActive' => true]),
            'featuredProducts' => $this->productRepository->count(['isFeatured' => true]),
            'outOfStockProducts' => $this->productRepository->count(['stock' => 0]),
            'lowStockProducts' => count($this->getLowStockProducts()),
        ];
    }

    // Méthodes privées pour la logique interne
    private function getValidCategory(int $categoryId): Category
    {
        $category = $this->categoryRepository->find($categoryId);
        if (!$category || !$category->isActive()) {
            throw new NotFoundHttpException('Catégorie non trouvée ou inactive');
        }

        return $category;
    }

    private function populateProductData(Product $product, array $data, Category $category): void
    {
        $product->setName($data['name'])
            ->setDescription($data['description'] ?? null)
            ->setPrice($data['price'])
            ->setStock($data['stock'])
            ->setCategory($category)
            ->setActive($data['isActive'] ?? true)
            ->setFeatured($data['isFeatured'] ?? false);
    }

    private function updateProductFields(Product $product, array $data): void
    {
        if (isset($data['name'])) {
            $product->setName($data['name']);
            $product->setSlug($this->generateUniqueSlug($data['name'], $product->getId()));
        }

        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }

        if (isset($data['price'])) {
            $product->setPrice($data['price']);
        }

        if (isset($data['stock'])) {
            $product->setStock($data['stock']);
        }

        if (isset($data['categoryId'])) {
            $category = $this->getValidCategory($data['categoryId']);
            $product->setCategory($category);
        }

        if (isset($data['isActive'])) {
            $product->setActive($data['isActive']);
        }

        if (isset($data['isFeatured'])) {
            $product->setFeatured($data['isFeatured']);
        }

        $this->handleImageUpload($product, $data);
    }

    private function handleImageUpload(Product $product, array $data): void
    {
        if (isset($data['imageFile']) && $data['imageFile'] instanceof UploadedFile) {
            $product->setImageFile($data['imageFile']);
        }
    }

    private function calculateNewStock(Product $product, int $quantity, string $operation): int
    {
        return match ($operation) {
            'set' => $quantity,
            'add' => $product->getStock() + $quantity,
            'subtract' => $this->validateSubtraction($product->getStock(), $quantity),
            default => throw new BadRequestHttpException('Opération invalide'),
        };
    }

    private function validateSubtraction(int $currentStock, int $quantity): int
    {
        $newStock = $currentStock - $quantity;
        if ($newStock < 0) {
            throw new BadRequestHttpException('Stock insuffisant');
        }

        return $newStock;
    }

    private function checkStockChange(Product $product, int $originalStock): void
    {
        $currentStock = $product->getStock();

        // Si le stock devient faible, dispatcher l'événement
        if ($currentStock <= self::STOCK_LOW_THRESHOLD && $originalStock > self::STOCK_LOW_THRESHOLD) {
            $this->eventDispatcher->dispatch(
                new ProductStockLowEvent($product, $currentStock, self::STOCK_LOW_THRESHOLD),
            );
        }
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = $this->slugger->slug($name)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            ++$counter;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
