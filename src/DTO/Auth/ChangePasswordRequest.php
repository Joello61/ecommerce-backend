<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordRequest
{
    #[Assert\NotBlank(message: 'Le mot de passe actuel est obligatoire')]
    public string $currentPassword;

    #[Assert\NotBlank(message: 'Le nouveau mot de passe est obligatoire')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères',
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre',
    )]
    #[Assert\NotEqualTo(
        propertyPath: 'currentPassword',
        message: 'Le nouveau mot de passe doit être différent de l\'ancien',
    )]
    public string $newPassword;

    #[Assert\NotBlank(message: 'La confirmation du mot de passe est obligatoire')]
    #[Assert\EqualTo(
        propertyPath: 'newPassword',
        message: 'Les mots de passe ne correspondent pas',
    )]
    public string $newPasswordConfirm;
}
