<?php

namespace fireclaytile\flatworld\models;

use craft\commerce\elements\Order;

/**
 * Contains metadata for an order.
 *
 * This class provides methods to calculate the total weight of an order and to determine
 * the types of products in the order. It also stores whether the order contains standard
 * products, merchandise, and sample products.
 *
 * @author      Fireclay Tile
 * @since       1.3.0
 */
class OrderMetaData
{
    /**
     * Indicates whether the order contains standard products.
     *
     * @var bool
     */
    public bool $containsStandardProducts;

    /**
     * Indicates whether the order contains merchandise.
     *
     * @var bool
     */
    public bool $containsMerchandise;

    /**
     * Indicates whether the order contains sample products.
     *
     * @var bool
     */
    public bool $containsSampleProducts;

    /**
     * The total weight of the order.
     *
     * @var float
     */
    public float $totalWeight;

    /**
     * Constructs an OrderMetaData object.
     *
     * Initializes properties and sets total weight and product types found.
     *
     * @param Order $order The order to get metadata for.
     * @param array $weightPerSquareFootPerProductTypes The weight per square foot for each product type.
     */
    public function __construct(
        Order $order,
        array $weightPerSquareFootPerProductTypes,
    ) {
        $this->containsStandardProducts = false;
        $this->containsMerchandise = false;
        $this->containsSampleProducts = false;
        $this->totalWeight = 0.0;

        if (!is_null($order) && !is_null($weightPerSquareFootPerProductTypes)) {
            $this->setTotalWeight($order, $weightPerSquareFootPerProductTypes);
            $this->setProductTypesFound($order);
        }
    }

    /**
     * Retrieves the total weight of the order.
     *
     * @return float Returns the total weight of the order.
     */
    public function getTotalWeight(): float
    {
        return $this->totalWeight;
    }

    /**
     * Sets the total weight of the order.
     *
     * Calculates the total weight based on the weight of each line item and the number of pieces.
     *
     * @param Order $order The order to set the total weight for.
     * @param array $weightPerSquareFootPerProductTypes The weight per square foot for each product type.
     */
    private function setTotalWeight(
        Order $order,
        array $weightPerSquareFootPerProductTypes,
    ): void {
        $index = 0;

        $pieces = $this->setPieces($order, $weightPerSquareFootPerProductTypes);

        foreach ($order->lineItems as $item) {
            $this->totalWeight += floatval($item->weight) * $pieces[$index];
            $index++;
        }
    }

    /**
     * Sets the product types found in the order.
     *
     * Iterates over each line item in the order and determines if it is a sample, merchandise, or standard product.
     * Updates the corresponding properties if at least one of each product type is found.
     *
     * @param Order $order The order to set the product types for.
     */
    private function setProductTypesFound(Order $order): void
    {
        $totalSample = 0;
        $totalStandard = 0;
        $totalMerchandise = 0;

        foreach ($order->lineItems as $item) {
            $isSample = isset($item->options['sample']);
            $isMerchandise =
                !empty($item->purchasable) &&
                !empty($item->purchasable->product) &&
                !empty($item->purchasable->product->type) &&
                !empty($item->purchasable->product->type->handle) &&
                $item->purchasable->product->type->handle === 'merchandise';

            if ($isSample) {
                $totalSample++;
            } elseif ($isMerchandise) {
                $totalMerchandise++;
            } else {
                $totalStandard++;
            }
        }

        if ($totalStandard > 0) {
            $this->containsStandardProducts = true;
        }

        if ($totalSample > 0) {
            $this->containsSampleProducts = true;
        }

        if ($totalMerchandise > 0) {
            $this->containsMerchandise = true;
        }
    }

    /**
     * Sets the pieces for each line item in the order.
     *
     * Iterates over each line item in the order and calculates the number of pieces based on the weight per square foot for the product type.
     * If the product type is handpainted, the number of pieces is set to the quantity of the line item.
     *
     * @param Order $order The order to set the pieces for.
     * @param array $weightPerSquareFootPerProductTypes The weight per square foot for each product type.
     * @return array Returns an array of the number of pieces for each line item in the order.
     */
    private function setPieces(
        Order $order,
        array $weightPerSquareFootPerProductTypes,
    ): array {
        $pieces = [];

        foreach ($order->lineItems as $item) {
            $weightPerSquareFoot = null;

            /**
             * We override the Craft Commerce/Postie quantity for each line item with the following formula to get current shipping cost calculation per square feet.
             * EXAMPLE:
             * - Weights: Tile is 4.5lbs, Brick is 5lbs, Glass is 3lbs.
             * - (4.5 / 0.69) * 25 = 163 (breakdown equals weight per sq ft / variant weight) * sq ft = number of pieces
             */
            if (
                !empty($item->purchasable) &&
                !empty($item->purchasable->product) &&
                !empty($item->purchasable->product->type) &&
                !empty($item->purchasable->product->type->handle)
            ) {
                $colorProductLinesCategorySlug = '';

                if (
                    !empty(
                        $item->purchasable->product->colorProductLinesCategory
                    ) &&
                    !empty(
                        $item->purchasable->product
                            ->colorProductLinesCategory[0]
                    ) &&
                    $item->purchasable->product->colorProductLinesCategory[0]
                        ->slug
                ) {
                    $colorProductLinesCategorySlug =
                        $item->purchasable->product
                            ->colorProductLinesCategory[0]->slug;
                }

                foreach (
                    $weightPerSquareFootPerProductTypes
                    as $weightPerSquareFootPerProductType
                ) {
                    $productTypeHandle = $weightPerSquareFootPerProductType[0];
                    $productLineSlug = $weightPerSquareFootPerProductType[1];
                    $value = $weightPerSquareFootPerProductType[2];

                    // Since we have a product line slug from the plugin settings, we need to check if our product line == x AND product type == y
                    // Example: QuickShip Seconds - Tile
                    if (
                        !empty($productLineSlug) &&
                        $colorProductLinesCategorySlug === $productLineSlug &&
                        $item->purchasable->product->type->handle ===
                            $productTypeHandle
                    ) {
                        $weightPerSquareFoot = $value;
                        break;

                        // Since we DONT have a product line slug from the plugin
                        // settings, we are only checking if our product type == x
                    } else {
                        // But we also need to account for handpainted since
                        // these are calculated per piece and not weight per
                        // square foot.
                        // Example: QuickShip Seconds - Handpainted
                        if (
                            $colorProductLinesCategorySlug === 'handpainted' &&
                            $item->purchasable->product->type->handle ===
                                $productTypeHandle
                        ) {
                            // Note we dont see $weightPerSquareFoot. Leave it
                            // null so our "per piece" logic below is
                            // triggered...but we do break out of the loop
                            break;

                            // Since we DONT have a product line slug, we are only
                            // checking if our product type == x
                            // Example: Brick
                        } elseif (
                            $item->purchasable->product->type->handle ===
                            $productTypeHandle
                        ) {
                            $weightPerSquareFoot = $value;
                            break;
                        }
                    }
                }

                if (!empty($weightPerSquareFoot)) {
                    $pieces[] = $this->calculatePieces(
                        $weightPerSquareFoot,
                        $item->weight,
                        $item->qty,
                    );
                } else {
                    $pieces[] = intval($item->qty);
                }
            } else {
                $pieces[] = intval($item->qty);
            }
        }

        return $pieces;
    }

    /**
     * Calculates the number of pieces for a line item.
     *
     * Divides the weight per square foot by the weight and multiplies by the quantity, then rounds up to the nearest whole number.
     *
     * @param float $weightPerSquareFoot The weight per square foot for the product type.
     * @param float $weight The weight of the line item.
     * @param int $qty The quantity of the line item.
     * @return int Returns the number of pieces for the line item.
     */
    private function calculatePieces($weightPerSquareFoot, $weight, $qty)
    {
        return ceil(
            (floatval($weightPerSquareFoot) / floatval($weight)) * intval($qty),
        );
    }
}
