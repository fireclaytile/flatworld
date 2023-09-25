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
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\variables
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
     * @param $orderId
     * @return array
     */
    public function getRates($orderId): array
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
            $this->_logMessage(__METHOD__, 'Found an order');

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

        $logger = new Logger(true);
        return $logger->logMessage($msg);
    }
}
