<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

class CategoryFixtures extends Fixture
{
    // Constantes pour les références
    public const CATEGORY_PREFIX = 'category_';

    private array $categories = [
        'Électronique' => 'Tous les appareils électroniques et gadgets high-tech',
        'Informatique' => 'Ordinateurs, composants et accessoires informatiques',
        'Smartphones' => 'Téléphones mobiles et accessoires',
        'Télévisions' => 'TV et systèmes home cinéma',
        'Audio' => 'Casques, enceintes et équipements audio',
        'Photo & Vidéo' => 'Appareils photo, caméras et accessoires',
        'Gaming' => 'Consoles de jeux et accessoires gaming',
        'Électroménager' => 'Appareils pour la maison',
        'Cuisine' => 'Équipements et ustensiles de cuisine',
        'Maison & Jardin' => 'Décoration et aménagement',
        'Mode Homme' => 'Vêtements et accessoires pour homme',
        'Mode Femme' => 'Vêtements et accessoires pour femme',
        'Chaussures' => 'Toutes les chaussures',
        'Accessoires' => 'Bijoux, montres et accessoires de mode',
        'Sport & Fitness' => 'Équipements sportifs et fitness',
        'Outdoor' => 'Camping, randonnée et activités extérieures',
        'Livres' => 'Tous types de livres',
        'Jouets & Enfants' => 'Jouets et articles pour enfants',
        'Beauté & Santé' => 'Produits de beauté et bien-être',
        'Automobile' => 'Accessoires et équipements auto',
        'Bricolage' => 'Outils et équipements de bricolage',
        'Bureau' => 'Fournitures et mobilier de bureau',
    ];

    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $index = 0;

        foreach ($this->categories as $name => $description) {
            $category = new Category();
            $category->setName($name);
            $category->setSlug($this->slugger->slug($name)->lower()->toString());
            $category->setDescription($description);
            $category->setActive(true);

            $manager->persist($category);

            // Ajouter une référence pour l'utiliser dans ProductFixtures
            $this->addReference(self::CATEGORY_PREFIX . $index, $category);

            ++$index;
        }

        $manager->flush();
    }
}
