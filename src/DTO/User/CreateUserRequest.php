<?php

declare(strict_types=1);

namespace App\DTO\User;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserRequest
{
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email doit être valide')]
    #[Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères')]
    public string $email;

    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes',
    )]
    public string $firstName;

    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes',
    )]
    public string $lastName;

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre',
    )]
    public string $password;

    #[Assert\Choice(
        choices: ['ROLE_USER', 'ROLE_ADMIN'],
        multiple: true,
        message: 'Rôle invalide',
    )]
    public array $roles = ['ROLE_USER'];

    #[Assert\Type('bool')]
    public bool $isVerified = false;

    #[Assert\File(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        maxSizeMessage: 'L\'image ne peut pas dépasser {{ limit }}',
        mimeTypesMessage: 'Veuillez télécharger une image valide (JPEG, PNG, GIF ou WebP)',
    )]
    public ?UploadedFile $avatarFile = null;
}
