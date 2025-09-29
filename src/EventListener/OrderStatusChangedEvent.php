<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use Symfony\Contracts\EventDispatcher\Event;

class OrderStatusChangedEvent extends Event
{
    public function __construct(
        private readonly Order $order,
        private readonly string $oldStatus,
        private readonly string $newStatus,
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
