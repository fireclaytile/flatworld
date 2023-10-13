<?php

namespace fireclaytile\flatworld\services;

use fireclaytile\flatworld\models\ShippingRequest;

/**
 * Interface for working with the Flatworld Rates API.
 *
 * @author Fireclay Tile
 * @since  0.8.0
 */
interface RatesApiInterface
{
    /**
     * Get rates from the API.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @return string
     */
    public function getRates(ShippingRequest $shippingRequest): string;

    /**
     * Tests the connection to the Rates API.
     *
     * @return boolean
     */
    public function testRatesConnection(): bool;
}
