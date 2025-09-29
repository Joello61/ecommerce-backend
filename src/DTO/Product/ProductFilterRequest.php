<?php

declare(strict_types=1);

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class ProductFilterRequest
{
    #[Assert\Type('integer', message: 'L\'ID de la catégorie doit être un nombre entier')]
    #[Assert\Positive(message: 'L\'ID de la catégorie doit être positif')]
    public ?int $categoryId = null;

    #[Assert\Type('numeric', message: 'Le prix minimum doit être un nombre')]
    #[Assert\PositiveOrZero(message: 'Le prix minimum doit être positif ou zéro')]
    public ?float $minPrice = null;

    #[Assert\Type('numeric', message: 'Le prix maximum doit être un nombre')]
    #[Assert\PositiveOrZero(message: 'Le prix maximum doit être positif ou zéro')]
    public ?float $maxPrice = null;

    #[Assert\Type('bool')]
    public ?bool $inStock = null;

    #[Assert\Type('bool')]
    public ?bool $isActive = null;

    #[Assert\Type('bool')]
    public ?bool $isFeatured = null;

    #[Assert\Length(
        min: 2,
        minMessage: 'Le terme de recherche doit contenir au moins {{ limit }} caractères',
    )]
    public ?string $search = null;

    #[Assert\Type('integer', message: 'La page doit être un nombre entier')]
    #[Assert\Positive(message: 'La page doit être positive')]
    public int $page = 1;

    #[Assert\Type('integer', message: 'La limite doit être un nombre entier')]
    #[Assert\Range(
        notInRangeMessage: 'La limite doit être comprise entre {{ min }} et {{ max }}',
        min: 1,
        max: 100,
    )]
    public int $limit = 20;

    #[Assert\Choice(
        choices: ['name', 'price', 'created_at', 'stock'],
        message: 'Champ de tri invalide',
    )]
    public string $sortBy = 'created_at';

    #[Assert\Choice(
        choices: ['asc', 'desc'],
        message: 'Direction de tri invalide',
    )]
    public string $sortOrder = 'desc';
}
