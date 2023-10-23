<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\variables;

use craft\helpers\Json;
use verbb\postie\Postie;
use craft\commerce\elements\Order;
use fireclaytile\flatworld\services\Logger;

/**
 * Class FlatworldVariable
 *
 * @author      Fireclay Tile
 * @since       0.8.0
 */
class FlatworldVariable
{
    /**
     * Whether or not to display debug messages.
     *
     * @var boolean|null
     */
    private bool|null $_loggingEnabled;

    /**
     * Returns an array of shipping rates for the given order.
     *
     * The rates are returned as an array. Generally, this will return either:
     *
     * 1. An empty array if the order is not found or has no line items.
     * 2. An array of 2 rates, with the first being the cheapest and the second being the fastest.
     * 3. An array of 1 rate if there is only 1 rate available, or if the cheapest and fastest rates are the same.
     *
     * Example:
     * Array
     *   (
     *     [FLAT_RATE_SHIPPING] => Array
     *       (
     *         [arrival] => 3-7 days
     *         [transitTime] => 3
     *         [arrivalDateText] => 2023/10/23
     *         [amount] => 8
     *         [type] => parcel
     *       )
     *
     *     [UPS_SECOND_DAY_AIR_AM] => Array
     *       (
     *         [arrival] => 1-3 days
     *         [transitTime] => 1
     *         [arrivalDateText] => 2023/10/19
     *         [amount] => 48.06
     *         [type] => parcel
     *       )
     *   )
     *
     * Notes:
     * - 'type' will either be 'parcel' or 'ltl'.
     *
     * @param int $orderId
     * @return array
     */
    public function getRates(int $orderId): array
    {
        $flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');

        $this->_loggingEnabled = $flatworld->getSetting('displayDebugMessages');
        $this->_logMessage(__METHOD__, 'Order ID: ' . $orderId);

        $order = Order::find()
            ->id($orderId)
            ->one();

        if (!empty($order)) {
            $rates = $flatworld->fetchShippingRates($order);

            if ($rates) {
                $this->_logMessage(
                    __METHOD__,
                    'Rates: ' . Json::encode($rates),
                );
                return $rates;
            }
            $this->_logMessage(__METHOD__, 'Rates were empty');
            return [];
        }

        $this->_logMessage(__METHOD__, 'Order was empty');
        return [];
    }

    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $method Method that called this function
     * @param string $message Message to log
     * @param string|null $uniqueId Unique ID for this call
     * @return boolean
     */
    private function _logMessage(
        string $method,
        string $message,
        ?string $uniqueId = null,
    ): bool {
        if (!$this->_loggingEnabled) {
            return false;
        }

        $msg = "{$method} :: {$message}";

        if ($uniqueId) {
            $msg = "{$method} :: {$uniqueId} :: {$message}";
        }

        return Logger::logMessage($msg, true);
    }
}
