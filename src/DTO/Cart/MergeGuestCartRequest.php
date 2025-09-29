<?php

declare(strict_types=1);

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class MergeGuestCartRequest
{
    #[Assert\NotBlank(message: 'Les données du panier invité sont obligatoires')]
    #[Assert\Type('array', message: 'Les données du panier doivent être un tableau')]
    #[Assert\All([
        new Assert\Collection([
            'productId' => [
                new Assert\NotBlank(message: 'L\'ID du produit est obligatoire'),
                new Assert\Type('integer', message: 'L\'ID du produit doit être un nombre entier'),
                new Assert\Positive(message: 'L\'ID du produit doit être positif'),
            ],
            'quantity' => [
                new Assert\NotBlank(message: 'La quantité est obligatoire'),
                new Assert\Type('integer', message: 'La quantité doit être un nombre entier'),
                new Assert\Positive(message: 'La quantité doit être positive'),
                new Assert\Range(
                    notInRangeMessage: 'La quantité doit être comprise entre {{ min }} et {{ max }}',
                    min: 1,
                    max: 99,
                ),
            ],
        ]),
    ])]
    public array $items;
}
