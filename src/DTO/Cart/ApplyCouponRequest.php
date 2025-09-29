<?php

declare(strict_types=1);

namespace App\DTO\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class ApplyCouponRequest
{
    #[Assert\NotBlank(message: 'Le code promo est obligatoire')]
    #[Assert\Type('string', message: 'Le code promo doit être une chaîne de caractères')]
    #[Assert\Length(
        min: 3,
        max: 20,
        minMessage: 'Le code promo doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le code promo ne peut pas dépasser {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9\-_]+$/',
        message: 'Le code promo ne peut contenir que des lettres majuscules, chiffres, tirets et underscores',
    )]
    public string $couponCode;
}
