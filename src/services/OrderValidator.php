<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\commerce\elements\Order;

/**
 * Service class OrderValidator. Provides methods for validating an order.
 *
 * @author      Fireclay Tile
 * @since       1.3.0
 */
class OrderValidator
{
    /**
     * The Commerce order object.
     *
     * @var Order
     */
    private Order $order;

    /**
     * Indicates whether logging is enabled.
     *
     * @var bool|null
     */
    private bool|null $loggingEnabled;

    /**
     * OrderValidator constructor.
     *
     * @param Order $order The order to be validated.
     * @param bool $loggingEnabled Whether logging is enabled or not.
     */
    public function __construct(Order $order, $loggingEnabled = false)
    {
        if (!is_null($order)) {
            $this->order = $order;
        }
        $this->loggingEnabled = $loggingEnabled;
    }

    /**
     * Sets the order to be validated.
     *
     * @param Order $order The order to validate.
     */
    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * Validates the order's line items and shipping address.
     *
     * @return bool Returns true if the order passes all checks, false otherwise.
     */
    public function validate(): bool
    {
        if (!$this->checkLineItems()) {
            return false;
        }

        if (!$this->checkLineItemRequiredFields()) {
            return false;
        }

        if (!$this->checkShippingAddress()) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the order has line items.
     *
     * @return bool Returns true if the order has line items, false otherwise.
     */
    private function checkLineItems(): bool
    {
        if (!$this->order->hasLineItems()) {
            $this->logMessage(__METHOD__, 'Has no line items yet, bailing out');

            return false;
        }

        $this->logMessage(__METHOD__, 'We have a line items, continuing');

        return true;
    }

    /**
     * Validates line items in the order for required fields.
     *
     * @return bool Returns true if all line items have required fields, false otherwise.
     */
    private function checkLineItemRequiredFields(): bool
    {
        $problems = false;
        $problemMessage = '';
        $devMode = Craft::$app->getConfig()->getGeneral()->devMode;

        foreach ($this->order->lineItems as $item) {
            if (
                !empty($item->purchasable) &&
                !empty($item->purchasable->product) &&
                !empty($item->purchasable->product->type) &&
                !empty($item->purchasable->product->type->handle)
            ) {
                if (isset($item->options['sample'])) {
                    // SKIP
                } elseif (
                    $item->purchasable->product->type->handle === 'addons'
                ) {
                    // SKIP
                } elseif (
                    $item->purchasable->product->type->handle === 'merchandise'
                ) {
                    // SKIP
                } else {
                    if (empty($item->weight) || empty($item->qty)) {
                        $problems = true;

                        $problemMessage .= "\nOrderID: {$this->order->id}, Product URL: {$item->purchasable->url}, Issue: Missing dimensions and/or weight";

                        $this->logMessage(
                            __METHOD__,
                            "Required Fields Missing. Order ID: {$this->order->id}, Product ID: {$item->purchasable->product->id}",
                        );
                    }
                }
            }
        }

        if ($problems && !empty($problemMessage)) {
            $this->logMessage(
                __METHOD__,
                "Invalid line items found: {$problemMessage}",
            );

            return false;
        }

        $this->logMessage(__METHOD__, 'We have a valid line items, continuing');

        return true;
    }

    /**
     * Checks if the order has a valid shipping address.
     *
     * @return bool Returns true if the order has a shipping address with a zip code, false otherwise.
     */
    private function checkShippingAddress(): bool
    {
        if (
            empty($this->order->shippingAddress) ||
            empty($this->order->shippingAddress->zipCode)
        ) {
            $this->logMessage(
                __METHOD__,
                'Has no shipping address yet, bailing out',
            );

            return false;
        }

        $this->logMessage(__METHOD__, 'We have a shipping address, continuing');

        return true;
    }

    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $message Message to log
     * @param string $method Method that called this function
     * @param string|null $uniqueId Unique ID for this call
     * @return boolean
     */
    private function logMessage(string $method, string $message): bool
    {
        if (!$this->loggingEnabled) {
            return false;
        }

        $msg = "{$method} :: {$message}";

        return Logger::logMessage($msg, true);
    }
}
