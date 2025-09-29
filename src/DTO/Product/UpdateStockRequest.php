<?php

declare(strict_types=1);

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateStockRequest
{
    #[Assert\NotBlank(message: 'La quantité est obligatoire')]
    #[Assert\Type('integer', message: 'La quantité doit être un nombre entier')]
    #[Assert\Range(
        notInRangeMessage: 'La quantité doit être comprise entre {{ min }} et {{ max }}',
        min: -99999,
        max: 99999,
    )]
    public int $quantity;

    #[Assert\NotBlank(message: 'L\'opération est obligatoire')]
    #[Assert\Choice(
        choices: ['set', 'add', 'subtract'],
        message: 'Opération invalide. Utilisez: set, add ou subtract',
    )]
    public string $operation;

    #[Assert\Length(
        max: 255,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères',
    )]
    public ?string $comment = null;
}
