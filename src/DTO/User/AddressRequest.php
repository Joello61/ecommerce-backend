<?php

declare(strict_types=1);

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class AddressRequest
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères',
    )]
    public string $firstName;

    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères',
    )]
    public string $lastName;

    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]
    #[Assert\Length(
        min: 5,
        max: 200,
        minMessage: 'L\'adresse doit contenir au moins {{ limit }} caractères',
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères',
    )]
    public string $street;

    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'La ville doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'La ville ne peut contenir que des lettres, espaces, tirets et apostrophes',
    )]
    public string $city;

    #[Assert\NotBlank(message: 'Le code postal est obligatoire')]
    #[Assert\Regex(
        pattern: '/^[0-9]{5}$/',
        message: 'Le code postal doit contenir exactement 5 chiffres',
    )]
    public string $zipCode;

    #[Assert\NotBlank(message: 'Le pays est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le pays doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères',
    )]
    #[Assert\Country(message: 'Le pays doit être un code pays valide')]
    public string $country = 'FR';

    #[Assert\Length(
        max: 20,
        maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
        message: 'Le numéro de téléphone doit être un numéro français valide',
    )]
    public ?string $phone = null;

    #[Assert\Type('bool')]
    public bool $isDefault = false;
}
