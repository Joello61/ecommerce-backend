<?php

declare(strict_types=1);

namespace App\DTO\Category;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCategoryRequest
{
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères',
    )]
    public ?string $name = null;

    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères',
    )]
    public ?string $description = null;

    #[Assert\Type('bool')]
    public ?bool $isActive = null;
}
