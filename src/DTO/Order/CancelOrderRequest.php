<?php

declare(strict_types=1);

namespace App\DTO\Order;

use Symfony\Component\Validator\Constraints as Assert;

class CancelOrderRequest
{
    #[Assert\NotBlank(message: 'La raison d\'annulation est obligatoire')]
    #[Assert\Choice(
        choices: [
            'customer_request',
            'payment_failed',
            'stock_unavailable',
            'shipping_issue',
            'other',
        ],
        message: 'Raison d\'annulation invalide',
    )]
    public string $reason;

    #[Assert\Length(
        max: 500,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères',
    )]
    public ?string $comment = null;

    #[Assert\Type('bool')]
    public bool $refundPayment = true;

    #[Assert\Type('bool')]
    public bool $restoreStock = true;
}
