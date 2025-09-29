<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\String\Slugger\SluggerInterface;

use function count;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    private array $productTemplates = [
        // Électronique
        ['Smartphone Premium', 699.99, 899.99],
        ['Tablette tactile', 349.99, 649.99],
        ['Ordinateur portable', 799.99, 1499.99],
        ['Écran PC', 249.99, 599.99],
        ['Clavier mécanique', 89.99, 199.99],
        ['Souris gaming', 49.99, 149.99],
        ['Webcam HD', 59.99, 129.99],
        ['Disque dur externe', 79.99, 199.99],

        // Audio
        ['Casque sans fil', 149.99, 349.99],
        ['Enceinte Bluetooth', 59.99, 199.99],
        ['Écouteurs intra-auriculaires', 29.99, 99.99],
        ['Barre de son', 199.99, 499.99],

        // Photo/Vidéo
        ['Appareil photo reflex', 599.99, 1299.99],
        ['Objectif photo', 299.99, 899.99],
        ['Trépied professionnel', 79.99, 249.99],
        ['Carte mémoire', 19.99, 89.99],

        // Gaming
        ['Console de jeux', 399.99, 549.99],
        ['Manette sans fil', 59.99, 79.99],
        ['Jeu vidéo', 49.99, 69.99],
        ['Casque gaming', 89.99, 299.99],

        // Électroménager
        ['Aspirateur robot', 299.99, 699.99],
        ['Cafetière automatique', 149.99, 399.99],
        ['Blender', 49.99, 149.99],
        ['Grille-pain', 29.99, 79.99],
        ['Bouilloire électrique', 24.99, 69.99],

        // Mode & Accessoires
        ['Montre connectée', 199.99, 449.99],
        ['Sac à dos', 39.99, 99.99],
        ['Lunettes de soleil', 49.99, 199.99],
        ['Portefeuille cuir', 29.99, 89.99],

        // Sport
        ['Tapis de yoga', 24.99, 59.99],
        ['Haltères', 19.99, 79.99],
        ['Tenue de sport', 39.99, 99.99],
        ['Bouteille isotherme', 19.99, 39.99],

        // Maison
        ['Lampe LED', 29.99, 79.99],
        ['Coussin décoratif', 14.99, 34.99],
        ['Plaid en laine', 39.99, 89.99],
    ];

    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $totalCategories = 22; // Nombre de catégories créées

        // Générer au moins 35 produits
        $productsToGenerate = max(35, count($this->productTemplates));

        for ($i = 0; $i < $productsToGenerate; ++$i) {
            // Utiliser le template ou générer aléatoirement
            if ($i < count($this->productTemplates)) {
                $template = $this->productTemplates[$i];
                $baseName = $template[0];
                $minPrice = $template[1];
                $maxPrice = $template[2];
            } else {
                $baseName = $faker->words(2, true);
                $minPrice = $faker->randomFloat(2, 19.99, 299.99);
                $maxPrice = $minPrice * $faker->randomFloat(2, 1.2, 2.5);
            }

            // Ajouter une touche unique au nom
            $brands = ['Pro', 'Elite', 'Premium', 'Plus', 'Max', 'Ultra', 'X', 'Classic'];
            $name = $baseName . ' ' . $faker->randomElement($brands);

            $product = new Product();
            $product->setName($name);
            $product->setSlug($this->slugger->slug($name . '-' . uniqid())->lower()->toString());
            $product->setDescription($faker->realText(200));
            $product->setPrice((string) $faker->randomFloat(2, $minPrice, $maxPrice));
            $product->setStock($faker->numberBetween(0, 100));
            $product->setActive($faker->boolean(90)); // 90% actifs
            $product->setFeatured($faker->boolean(20)); // 20% en vedette

            // Assigner une catégorie aléatoire
            $categoryIndex = $faker->numberBetween(0, $totalCategories - 1);
            /** @var Category $category */
            $category = $this->getReference(CategoryFixtures::CATEGORY_PREFIX . $categoryIndex, Category::class);
            $product->setCategory($category);

            $manager->persist($product);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }
}
