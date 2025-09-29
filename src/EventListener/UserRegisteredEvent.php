<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserRegisteredEvent extends Event
{
    public function __construct(
        private readonly User $user,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }
}
