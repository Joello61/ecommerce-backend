<?php

declare(strict_types=1);

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCartItemRequest
{
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
