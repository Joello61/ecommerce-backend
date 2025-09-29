<?php

declare(strict_types=1);

namespace App\DTO\Order;

use Symfony\Component\Validator\Constraints as Assert;

class CreateOrderRequest
{
    #[Assert\NotBlank(message: 'L\'adresse de livraison est obligatoire')]
    #[Assert\Type('integer', message: 'L\'ID de l\'adresse de livraison doit être un nombre entier')]
    #[Assert\Positive(message: 'L\'ID de l\'adresse de livraison doit être positif')]
    public int $shippingAddressId;

    #[Assert\NotBlank(message: 'L\'adresse de facturation est obligatoire')]
    #[Assert\Type('integer', message: 'L\'ID de l\'adresse de facturation doit être un nombre entier')]
    #[Assert\Positive(message: 'L\'ID de l\'adresse de facturation doit être positif')]
    public int $billingAddressId;

    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères',
    )]
    public ?string $notes = null;

    #[Assert\Choice(
        choices: ['card', 'paypal', 'bank_transfer'],
        message: 'Méthode de paiement invalide',
    )]
    public string $paymentMethod = 'card';

    #[Assert\Type('bool')]
    public bool $acceptTerms = false;

    #[Assert\Type('bool')]
    public bool $saveAddresses = false;
}
