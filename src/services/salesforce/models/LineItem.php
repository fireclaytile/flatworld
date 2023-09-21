<?php

namespace fireclaytile\flatworld\services\salesforce\models;

class LineItem
{
    public string $productId;
    public int $quantity;

    public function __construct(string $productId, int $quantity)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }
}
