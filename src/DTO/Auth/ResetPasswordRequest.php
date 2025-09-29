<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordRequest
{
    #[Assert\NotBlank(message: 'Le token est obligatoire')]
    public string $token;

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

    #[Assert\NotBlank(message: 'La confirmation du mot de passe est obligatoire')]
    #[Assert\EqualTo(
        propertyPath: 'password',
        message: 'Les mots de passe ne correspondent pas',
    )]
    public string $passwordConfirm;
}
