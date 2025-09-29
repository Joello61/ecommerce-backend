<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use Symfony\Contracts\EventDispatcher\Event;

class ProductStockLowEvent extends Event
{
    public function __construct(
        private readonly Product $product,
        private readonly int $currentStock,
        private readonly int $threshold = 5,
    ) {}

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getCurrentStock(): int
    {
        return $this->currentStock;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }
}
