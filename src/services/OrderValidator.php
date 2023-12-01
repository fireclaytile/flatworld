<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderNotice;

use fireclaytile\flatworld\models\OrderMetaData;

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
     * The weight per square foot for each product type.
     *
     * @var array
     */
    private array $weightPerSquareFootPerProductTypes;

    /**
     * The total maximum weight for an order.
     *
     * @var float
     */
    private float $totalMaxWeight;

    /**
     * The message to display when the weight limit is exceeded.
     *
     * @var string
     */
    private string $weightLimitMessage;

    /**
     * Indicates whether logging is enabled.
     *
     * @var bool|null
     */
    private bool|null $loggingEnabled;

    /**
     * Constructs an OrderValidator object.
     *
     * Initializes properties and sets the order, weight per square foot per product types, total max weight, weight limit message, and logging enabled status if provided.
     *
     * @param Order $order The order to validate.
     * @param array $weightPerSquareFootPerProductTypes The weight per square foot for each product type.
     * @param mixed $totalMaxWeight The total maximum weight for an order.
     * @param string $weightLimitMessage The message to display when the weight limit is exceeded.
     * @param bool $loggingEnabled Indicates whether logging is enabled.
     */
    public function __construct(
        Order $order,
        array $weightPerSquareFootPerProductTypes,
        mixed $totalMaxWeight,
        string $weightLimitMessage,
        $loggingEnabled = false,
    ) {
        if (!is_null($order)) {
            $this->order = $order;
        }

        $this->weightPerSquareFootPerProductTypes = $weightPerSquareFootPerProductTypes;
        $this->totalMaxWeight = floatval($totalMaxWeight);
        $this->weightLimitMessage = $weightLimitMessage;
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
     * Validates the order.
     *
     * Checks the line items, required fields, and shipping address of the order. Filters out addons and creates an OrderMetaData object.
     * Ensures the total weight of the order is not zero and within the weight limit.
     *
     * @return OrderMetaData|false Returns an OrderMetaData object if the order is valid, false otherwise.
     */
    public function validate(): OrderMetaData|false
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

        $this->filterOutAddons();

        $orderMetaData = new OrderMetaData(
            $this->order,
            $this->weightPerSquareFootPerProductTypes,
        );

        // ensure there is a weight.
        if (!$this->checkTotalWeight($orderMetaData)) {
            return false;
        }

        // ensure the weight is within the limit.
        if (!$this->checkWeightLimit($orderMetaData)) {
            return false;
        }

        return $orderMetaData;
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
     * Filters out addons from the order's lineItems.
     *
     * @return void
     */
    private function filterOutAddons(): void
    {
        $this->order->lineItems = array_filter(
            $this->order->lineItems,
            function ($item) {
                $isAddOn =
                    !empty($item->purchasable) &&
                    !empty($item->purchasable->product) &&
                    !empty($item->purchasable->product->type) &&
                    !empty($item->purchasable->product->type->handle) &&
                    $item->purchasable->product->type->handle === 'addons';

                return !$isAddOn;
            },
        );
    }

    /**
     * Checks the total weight of the order.
     *
     * Ensures the total weight of the order is not zero.
     *
     * @param OrderMetaData $orderMetaData The metadata of the order.
     * @return bool Returns true if the total weight of the order is not zero, false otherwise.
     */
    private function checkTotalWeight(OrderMetaData $orderMetaData): bool
    {
        if ($orderMetaData->getTotalWeight() <= 0.0) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the weight limit of the order has been reached.
     *
     * Clears any existing notices related to the shipping method and weight limit.
     * If the weight limit has been reached, logs a message, creates a notice, and adds it to the order.
     *
     * @param OrderMetaData $orderMetaData The metadata of the order.
     * @return bool Returns true if the weight limit has not been reached, false otherwise.
     */
    private function checkWeightLimit(OrderMetaData $orderMetaData): bool
    {
        $this->order->clearNotices(
            'shippingMethodChanged',
            'shippingWeightLimit',
        );

        if ($this->weightLimitReached($orderMetaData)) {
            $this->logMessage(__METHOD__, 'Weight limit reached');

            $notice = Craft::createObject([
                'class' => OrderNotice::class,
                'type' => 'shippingMethodChanged',
                'attribute' => 'shippingWeightLimit',
                'message' => $this->weightLimitMessage,
            ]);

            $this->order->addNotice($notice);

            return false;
        }

        return true;
    }

    /**
     * Checks if the weight limit of the order has been reached.
     *
     * Compares the total weight of the order to the maximum allowed weight.
     *
     * @param OrderMetaData $orderMetaData The metadata of the order.
     * @return bool Returns true if the weight limit has been reached, false otherwise.
     */
    private function weightLimitReached(OrderMetaData $orderMetaData): bool
    {
        return $orderMetaData->getTotalWeight() >
            floatval($this->totalMaxWeight);
    }

    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $message Message to log
     * @param string $method Method that called this function
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
