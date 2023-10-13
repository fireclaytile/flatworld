<?php

namespace fireclaytile\flatworld\models;

use fireclaytile\flatworld\models\LineItem;

/**
 * ShippingRequest model class.
 *
 * Used to store details about a shipping request.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 */
class ShippingRequest
{
    /**
     * Zip code.
     *
     * @var string
     */
    public string $zipCode;

    /**
     * Lift gate?
     *
     * @var bool
     */
    public bool $liftGate;

    /**
     * Order type. Sample or Order
     *
     * @var string
     */
    public string $orderType;

    /**
     * Line items from the order.
     *
     * @var array
     */
    public array $lineItems;

    /**
     * ShippingRequest constructor.
     *
     * @param string $zipCode Zip code
     * @param bool $liftGate Lift gate?
     * @param string $orderType Order type. Sample or Order
     * @param array $lineItems Line items from the order
     */
    public function __construct(
        string $zipCode,
        bool $liftGate,
        string $orderType,
        array $lineItems,
    ) {
        $this->zipCode = $zipCode;
        $this->liftGate = $liftGate;
        $this->orderType = $orderType;
        $this->lineItems = $lineItems;
    }

    public function addLineItem(LineItem $lineItem)
    {
        $this->lineItems[] = $lineItem;
    }
}
