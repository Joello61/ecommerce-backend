<?php

declare(strict_types=1);

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class AddToCartRequest
{
    #[Assert\NotBlank(message: 'L\'ID du produit est obligatoire')]
    #[Assert\Type('integer', message: 'L\'ID du produit doit être un nombre entier')]
    #[Assert\Positive(message: 'L\'ID du produit doit être positif')]
    public int $productId;

    #[Assert\NotBlank(message: 'La quantité est obligatoire')]
    #[Assert\Type('integer', message: 'La quantité doit être un nombre entier')]
    #[Assert\Positive(message: 'La quantité doit être positive')]
    #[Assert\Range(
        notInRangeMessage: 'La quantité doit être comprise entre {{ min }} et {{ max }}',
        min: 1,
        max: 99,
    )]
    public int $quantity;
}
