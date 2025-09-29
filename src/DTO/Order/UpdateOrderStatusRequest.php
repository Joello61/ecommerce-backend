<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\Entity\Order;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateOrderStatusRequest
{
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(
        choices: [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ],
        message: 'Statut de commande invalide',
    )]
    public string $status;

    #[Assert\Length(
        max: 500,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères',
    )]
    public ?string $comment = null;

    #[Assert\Type('bool')]
    public bool $notifyCustomer = true;
}
