<?php

declare(strict_types=1);

namespace App\DTO\Product;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CreateProductRequest
{
    #[Assert\NotBlank(message: 'Le nom du produit est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 200,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères',
    )]
    public string $name;

    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères',
    )]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Le prix est obligatoire')]
    #[Assert\Type('numeric', message: 'Le prix doit être un nombre')]
    #[Assert\Positive(message: 'Le prix doit être positif')]
    #[Assert\Range(
        notInRangeMessage: 'Le prix doit être compris entre {{ min }}€ et {{ max }}€',
        min: 0.01,
        max: 999999.99,
    )]
    public float $price;

    #[Assert\NotBlank(message: 'Le stock est obligatoire')]
    #[Assert\Type('integer', message: 'Le stock doit être un nombre entier')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif')]
    #[Assert\Range(
        notInRangeMessage: 'Le stock doit être compris entre {{ min }} et {{ max }}',
        min: 0,
        max: 99999,
    )]
    public int $stock;

    #[Assert\NotBlank(message: 'La catégorie est obligatoire')]
    #[Assert\Type('integer', message: 'L\'ID de la catégorie doit être un nombre entier')]
    #[Assert\Positive(message: 'L\'ID de la catégorie doit être positif')]
    public int $categoryId;

    #[Assert\Type('bool')]
    public bool $isActive = true;

    #[Assert\Type('bool')]
    public bool $isFeatured = false;

    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        maxSizeMessage: 'L\'image ne peut pas dépasser {{ limit }}',
        mimeTypesMessage: 'Veuillez télécharger une image valide (JPEG, PNG, WebP ou GIF)',
    )]
    public ?UploadedFile $imageFile = null;
}
