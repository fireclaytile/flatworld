<?php

namespace fireclaytile\flatworld\services\salesforce\models;

/**
 * ShippingRequest model class.
 *
 * Used to store details about a shipping request.
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services\salesforce\models
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
}
