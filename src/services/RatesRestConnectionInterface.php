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
     * Gets shipping rates.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @return mixed
     */
    public function getRates(ShippingRequest $shippingRequest): mixed;
}
