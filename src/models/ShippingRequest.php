<?php

namespace fireclaytile\flatworld\models;

use craft\commerce\elements\Order;
use fireclaytile\flatworld\models\OrderMetaData;

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
     * Creates a ShippingRequest from an Order.
     *
     * Initializes the line items, zip code, order type, and lift gate status based on the order
     * and order metadata.
     *
     * If the order contains standard products, the order type is set to 'Order'. If the order has
     * a truck lift charge and lift gate rates are enabled, the lift gate status is set to true.
     *
     * @param Order $order The order to create the ShippingRequest from.
     * @param OrderMetaData $orderMetaData The metadata of the order.
     * @param bool $enableLiftGateRates Indicates whether lift gate rates are enabled.
     */
    public function __construct(
        Order $order,
        OrderMetaData $orderMetaData,
        bool $enableLiftGateRates = false,
    ) {
        $this->zipCode = $order->shippingAddress->postalCode;
        $this->liftGate = false;
        $this->orderType = 'Sample';
        $this->lineItems = [];

        foreach ($order->lineItems as $item) {
            $this->addLineItem(
                new LineItem($this->setLineItemProductId($item), $item->qty),
            );
        }

        $this->orderType = 'Sample';
        if ($orderMetaData->containsStandardProducts) {
            $this->orderType = 'Order';
        }

        if ($order->truckLiftCharge && $enableLiftGateRates) {
            $this->liftGate = true;
        }
    }

    private function addLineItem(LineItem $lineItem)
    {
        $this->lineItems[] = $lineItem;
    }

    /**
     * Determines the productId for an order line item.
     *
     * @param mixed $row A LineItem from the order.
     * @return string
     */
    private function setLineItemProductId($row): string
    {
        // This is taken directly from the fct Salesforce plugin.
        if (
            isset($row->options['masterSalesforceId']) and
            empty($row->options['masterSalesforceId'])
        ) {
            return '';
        }

        $commerceService = \craft\commerce\Plugin::getInstance();

        // Get product.
        $product = $commerceService
            ->getProducts()
            ->getProductById($row->getPurchasable()->productId);

        $productId = $product->sizeSalesforceId
            ? $product->sizeSalesforceId
            : '01t340000043WQ6';

        //  customMosaicTimestamp
        if (
            isset($row->options['customMosaicTimestamp']) and
            !empty($row->options['customMosaicTimestamp'])
        ) {
            $productId = $row->options['salesforceId']
                ? $row->options['salesforceId']
                : 'a1s34000001n6bo';
        }

        // Deal with masterSalesforceId
        if (
            isset($row->options['masterSalesforceId']) and
            !empty($row->options['masterSalesforceId'])
        ) {
            $productId = $row->options['masterSalesforceId'];
        }

        // For generalSamples products set the productId here
        if (
            isset($row->options['generalSampleSalesforceId']) and
            !empty($row->options['generalSampleSalesforceId'])
        ) {
            $productId = $row->options['generalSampleSalesforceId'];
        }

        return $productId;
    }
}
