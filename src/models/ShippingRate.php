<?php

namespace fireclaytile\flatworld\models;

/**
 * Model class for a shipping rate.
 *
 * @author      Fireclay Tile
 * @since       0.9.14
 */
class ShippingRate
{
    /**
     * @var string|null The service level of the shipping rate.
     */
    public string|null $serviceLevel;

    /**
     * @var string|null The name of the carrier for the shipping rate.
     */
    public string|null $carrierName;

    /**
     * @var float The total cost of the shipping rate.
     */
    public $total;

    /**
     * @var int|null The number of transit days for the shipping rate.
     */
    public int|null $transitDays;

    /**
     * @var string|null The estimated delivery date for the shipping rate.
     */
    public string|null $estimatedDeliveryDate;

    /**
     * @var string|null The estimated delivery time for the shipping rate.
     */
    public string|null $estimatedDeliveryTime;

    /**
     * @var string The type of the shipping rate.
     */
    public $type;

    /**
     * Creates a new `ShippingRate` object from an array of data.
     *
     * @param array $data The data to create the `ShippingRate` object from.
     * @return ShippingRate The new `ShippingRate` object.
     */
    public static function fromArray(array $data): ShippingRate
    {
        $shippingRate = new ShippingRate();
        $shippingRate->serviceLevel = $data['ServiceLevel'];
        $shippingRate->carrierName = $data['CarrierName'];
        $shippingRate->total = $data['Total'];
        $shippingRate->transitDays = $data['TransitDays'];
        $shippingRate->estimatedDeliveryDate =
            $data['EstimatedDeliveryDate'] ?? null;
        $shippingRate->estimatedDeliveryTime =
            $data['EstimatedDeliveryTime'] ?? null;
        $shippingRate->type = $data['Type'];

        return $shippingRate;
    }

    /**
     * Creates an array of `ShippingRate` objects from a JSON string.
     *
     * @param string $json The JSON string to create the `ShippingRate` objects from.
     * @return array An array of `ShippingRate` objects.
     */
    public static function fromJson(string $json): array
    {
        $data = json_decode($json, true);
        $shippingRates = [];

        foreach ($data as $rate) {
            $shippingRates[] = self::fromArray($rate);
        }

        return $shippingRates;
    }

    /**
     * Converts the `serviceLevel` or `carrierName` property into uppercase with underscores in place of
     * spaces, depending on the `type` property. This would match one of the `carrierClassOfServices`
     * handles in the Flatworld Postie config. Ex: `UPS Next Day Air` would become `UPS_NEXT_DAY_AIR`.
     *
     * @return string
     */
    public function getServiceHandle(): string
    {
        $toConvert = $this->serviceLevel;

        if ($this->type == 'ltl') {
            $toConvert = $this->carrierName;
        }

        return str_replace(' ', '_', strtoupper($toConvert));
    }

    /**
     * Returns a string of the estimated arrival time based on the transit days.
     *
     * @return string
     */
    public function getArrivalEstimationString(): string
    {
        $transitDays = $this->transitDays;

        if (!$transitDays || $transitDays <= 0) {
            return '';
        }

        // This is how it was in the Pacejet plugin...
        if ($transitDays >= 21) {
            return '21 days or more';
        } elseif ($transitDays >= 14) {
            return '14-21 days';
        } elseif ($transitDays >= 7) {
            return '7-14 days';
        } elseif ($transitDays >= 3) {
            return '3-7 days';
        }
        return '1-3 days';
    }
}
