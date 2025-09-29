<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\Entity\Order;
use Symfony\Component\Validator\Constraints as Assert;

class OrderFilterRequest
{
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
    public ?string $status = null;

    #[Assert\Date(message: 'La date de début doit être une date valide')]
    public ?string $startDate = null;

    #[Assert\Date(message: 'La date de fin doit être une date valide')]
    public ?string $endDate = null;

    #[Assert\Type('numeric', message: 'Le montant minimum doit être un nombre')]
    #[Assert\PositiveOrZero(message: 'Le montant minimum doit être positif ou zéro')]
    public ?float $minAmount = null;

    #[Assert\Type('numeric', message: 'Le montant maximum doit être un nombre')]
    #[Assert\PositiveOrZero(message: 'Le montant maximum doit être positif ou zéro')]
    public ?float $maxAmount = null;

    #[Assert\Type('integer', message: 'L\'ID utilisateur doit être un nombre entier')]
    #[Assert\Positive(message: 'L\'ID utilisateur doit être positif')]
    public ?int $userId = null;

    #[Assert\Length(
        min: 3,
        minMessage: 'Le terme de recherche doit contenir au moins {{ limit }} caractères',
    )]
    public ?string $search = null;

    #[Assert\Type('integer', message: 'La page doit être un nombre entier')]
    #[Assert\Positive(message: 'La page doit être positive')]
    public int $page = 1;

    #[Assert\Type('integer', message: 'La limite doit être un nombre entier')]
    #[Assert\Range(
        notInRangeMessage: 'La limite doit être comprise entre {{ min }} et {{ max }}',
        min: 1,
        max: 100,
    )]
    public int $limit = 20;

    #[Assert\Choice(
        choices: ['created_at', 'updated_at', 'total_price', 'order_number'],
        message: 'Champ de tri invalide',
    )]
    public string $sortBy = 'created_at';

    #[Assert\Choice(
        choices: ['asc', 'desc'],
        message: 'Direction de tri invalide',
    )]
    public string $sortOrder = 'desc';
}
