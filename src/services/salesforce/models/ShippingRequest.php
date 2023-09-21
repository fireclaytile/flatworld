<?php

namespace fireclaytile\flatworld\services\salesforce\models;

class ShippingRequest
{
    public string $zipCode;
    public bool $liftGate;
    public array $lineItems;

    public function __construct(
        string $zipCode,
        bool $liftGate,
        array $lineItems,
    ) {
        $this->zipCode = $zipCode;
        $this->liftGate = $liftGate;
        $this->lineItems = $lineItems;
    }
}
