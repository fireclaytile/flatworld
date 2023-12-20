<?php

namespace fireclaytile\flatworld\services;

use fireclaytile\flatworld\models\ShippingRequest;

/**
 * RatesRestConnectionInterface
 *
 * Interface for making REST API calls.
 */
interface RatesRestConnectionInterface
{
    /**
     * Connects to the REST API.
     *
     * @return void
     */
    public function connect(): void;

    /**
     * Gets shipping rates.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @return mixed
     */
    public function getRates(ShippingRequest $shippingRequest): mixed;
}
