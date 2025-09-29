<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use Symfony\Contracts\EventDispatcher\Event;

class OrderCreatedEvent extends Event
{
    public function __construct(
        private readonly Order $order,
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }
}
