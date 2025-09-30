<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Product\ProductFilterRequest;
use App\Repository\CategoryRepository;
use App\Service\ProductService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use function count;
use function strlen;

#[Route('/api/products', name: 'api_products_')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function getProducts(#[MapQueryString] ProductFilterRequest $filters): JsonResponse
    {
        try {
            $result = $this->productService->getProductsWithFilters([
                'category' => $filters->categoryId ? $this->categoryRepository->find($filters->categoryId) : null,
                'minPrice' => $filters->minPrice,
                'maxPrice' => $filters->maxPrice,
                'inStock' => $filters->inStock,
                'featured' => $filters->isFeatured,
                'search' => $filters->search,
            ], $filters->page, $filters->limit);

            $productsData = array_map([$this, 'formatProductData'], $result['products']);

            return $this->json([
                'success' => true,
                'data' => [
                    'products' => $productsData,
                    'pagination' => $result['pagination'],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération produits', [
                'filters' => get_object_vars($filters),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des produits',
            ], 500);
        }
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getProduct(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProduct($id);

            if (!$product->isActive()) {
                throw new NotFoundHttpException('Produit non disponible');
            }

            $similarProducts = $this->productService->getSimilarProducts($id, 4);
            $similarProductsData = array_map([$this, 'formatProductData'], $similarProducts);

            return $this->json([
                'success' => true,
                'data' => [
                    'product' => $this->formatProductData($product, true),
                    'similarProducts' => $similarProductsData,
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération produit', [
                'product_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du produit',
            ], 500);
        }
    }

    #[Route('/featured', name: 'featured', methods: ['GET'])]
    public function getFeaturedProducts(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query->get('limit', 8);

            $products = $this->productService->getFeaturedProducts($limit);
            $productsData = array_map([$this, 'formatProductData'], $products);

            return $this->json([
                'success' => true,
                'data' => [
                    'products' => $productsData,
                    'total' => count($productsData),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération produits en vedette', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/best-sellers', name: 'best_sellers', methods: ['GET'])]
    public function getBestSellers(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query->get('limit', 8);

            $products = $this->productService->getBestSellers($limit);
            $productsData = array_map(function ($item) {
                $product = $item[0]; // Le produit est le premier élément du résultat
                $data = $this->formatProductData($product);
                $data['totalSold'] = $item['totalSold'] ?? 0;

                return $data;
            }, $products);

            return $this->json([
                'success' => true,
                'data' => [
                    'products' => $productsData,
                    'total' => count($productsData),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération meilleures ventes', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function searchProducts(#[MapQueryString] ProductFilterRequest $filters): JsonResponse
    {
        if (empty($filters->search) || strlen($filters->search) < 2) {
            return $this->json([
                'success' => false,
                'message' => 'Le terme de recherche doit contenir au moins 2 caractères',
            ], 400);
        }

        try {
            $products = $this->productService->searchProducts($filters->search, $filters->categoryId);
            $productsData = array_map([$this, 'formatProductData'], $products);

            return $this->json([
                'success' => true,
                'data' => [
                    'products' => $productsData,
                    'total' => count($productsData),
                    'query' => $filters->search,
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur recherche produits', [
                'query' => $filters->search,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la recherche',
            ], 500);
        }
    }

    #[Route('/categories/{categoryId}', name: 'by_category', requirements: ['categoryId' => '\d+'], methods: ['GET'])]
    public function getProductsByCategory(int $categoryId): JsonResponse
    {
        try {
            $products = $this->productService->getProductsByCategory($categoryId);
            $productsData = array_map([$this, 'formatProductData'], $products);

            $category = $this->categoryRepository->find($categoryId);
            if (!$category || !$category->isActive()) {
                throw new NotFoundHttpException('Catégorie non trouvée');
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'products' => $productsData,
                    'total' => count($productsData),
                    'category' => [
                        'id' => $category->getId(),
                        'name' => $category->getName(),
                        'description' => $category->getDescription(),
                        'slug' => $category->getSlug(),
                    ],
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération produits par catégorie', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/categories', name: 'categories_list', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        try {
            $categories = $this->categoryRepository->findActiveCategories();

            $categoriesData = array_map(static function ($category) {
                return [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'description' => $category->getDescription(),
                    'slug' => $category->getSlug(),
                    'productsCount' => $category->getProducts()->count(),
                ];
            }, $categories);

            return $this->json([
                'success' => true,
                'data' => [
                    'categories' => $categoriesData,
                    'total' => count($categoriesData),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur récupération catégories', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    #[Route('/check-availability/{id}', name: 'check_availability', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function checkAvailability(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProduct($id);

            return $this->json([
                'success' => true,
                'data' => [
                    'available' => $product->isInStock() && $product->isActive(),
                    'stock' => $product->getStock(),
                    'isActive' => $product->isActive(),
                    'productId' => $product->getId(),
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'message' => 'Produit non trouvé',
            ], 404);
        } catch (Exception $e) {
            $this->logger->error('Erreur vérification disponibilité', [
                'product_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
            ], 500);
        }
    }

    private function formatProductData($product, bool $detailed = false): array
    {
        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'isActive' => $product->isActive(),
            'isFeatured' => $product->isFeatured(),
            'isInStock' => $product->isInStock(),
            'imageName' => $product->getImageName(),
            'category' => [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
                'slug' => $product->getCategory()->getSlug(),
            ],
            'createdAt' => $product->getCreatedAt(),
        ];

        if ($detailed) {
            $data['description'] = $product->getDescription();
            $data['updatedAt'] = $product->getUpdatedAt();
        }

        return $data;
    }
}
