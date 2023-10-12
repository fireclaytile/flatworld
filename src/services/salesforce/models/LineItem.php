<?php

namespace fireclaytile\flatworld\services\salesforce\models;

/**
 * LineItem model class.
 *
 * Used to store details about a line item within a ShippingRequest's lineItems array.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 */
class LineItem
{
    /**
     * Product ID.
     *
     * @var string
     */
    public string $productId;

    /**
     * Quantity.
     *
     * @var int
     */
    public int $quantity;

    /**
     * LineItem constructor.
     *
     * @param string $productId Product ID
     * @param int $quantity Quantity
     */
    public function __construct(string $productId, int $quantity)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }
}
