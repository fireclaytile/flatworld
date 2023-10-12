<?php

namespace fireclaytile\flatworld\services;

use DateTime;
use DateTimeZone;
use fireclaytile\flatworld\models\ShippingRate;

/**
 * Service class for working with shipping rates that are returned from our API.
 * You can instantiate this with some rates JSON and it will allow you to access cheapest and fastest rates, etc.
 *
 * @author      Fireclay Tile
 * @since       0.9.14
 */
class ShippingRates
{
    // private variable of an array of `ShippingRate` objects
    private array $_rates;

    // cheapest rate
    private ShippingRate $_cheapestRate;

    // fastest rate
    private ShippingRate $_fastestRate;

    /**
     * ShippingRates constructor.
     *
     * @param string|null $ratesJson `JSON` string of rates
     */
    public function __construct(?string $ratesJson = null)
    {
        if ($ratesJson == null) {
            $ratesJson = '[]';
        }

        $this->setRates($ratesJson);
        $this->setFastestRate();
        $this->setCheapestRate();
    }

    /**
     * Set the rates array from a `JSON` string.
     *
     * @param string $ratesJson
     */
    public function setRates(string $ratesJson): void
    {
        $rates = json_decode($ratesJson, true);

        foreach ($rates as $rate) {
            $this->_rates[] = ShippingRate::fromArray($rate);
        }
    }

    /**
     * Get the rates.
     *
     * @return array
     */
    public function getRates(): array
    {
        return $this->_rates;
    }

    /**
     * Sets the cheapest rate that exists from the rates array. Also sets the cheapest rate service level handle.
     *
     * @return void
     */
    public function setCheapestRate(): void
    {
        $cheapestRate = $this->_rates[0];

        foreach ($this->_rates as $rate) {
            if ($rate->total < $cheapestRate->total) {
                $cheapestRate = $rate;
            }
        }

        $this->_cheapestRate = $cheapestRate;
    }

    /**
     * Get the cheapest rate.
     *
     * @return ShippingRate
     */
    public function getCheapestRate(): ShippingRate
    {
        return $this->_cheapestRate;
    }

    /**
     * Sets the fastest rate that exists from the array of rates. Also sets the fastest rate service level handle.
     *
     * @return void
     */
    public function setFastestRate(): void
    {
        $fastestRate = null;

        foreach ($this->_rates as $rate) {
            if ($fastestRate === null) {
                $fastestRate = $rate;
            } else {
                $fastestRate = $this->_compareShippingRateDeliveryTimes(
                    $rate,
                    $fastestRate,
                );
            }
        }

        $this->_fastestRate = $fastestRate;
    }

    /**
     * Get the fastest rate.
     *
     * @return ShippingRate|null
     */
    public function getFastestRate(): ?ShippingRate
    {
        return $this->_fastestRate;
    }

    /**
     * Compare two rates and return the one which will delivered the soonest.
     *
     * @param ShippingRate $rate1
     * @param ShippingRate $rate2
     * @return ShippingRate
     */
    private function _compareShippingRateDeliveryTimes(
        ShippingRate $rate1,
        ShippingRate $rate2,
    ): ShippingRate {
        if (
            $rate1->estimatedDeliveryDate !== null &&
            $rate2->estimatedDeliveryDate !== null
        ) {
            return $this->_compareEstimatedDeliveryDates($rate1, $rate2);
        }

        if ($rate1->transitDays !== null && $rate2->transitDays !== null) {
            return $this->_compareTransitDays($rate1, $rate2);
        }

        return $rate2;
    }

    /**
     * Compare two rates and return the one with the quicker estimated delivery date and time
     *
     * @param ShippingRate $rate1
     * @param ShippingRate $rate2
     * @return ShippingRate
     */
    private function _compareEstimatedDeliveryDates(
        ShippingRate $rate1,
        ShippingRate $rate2,
    ): ShippingRate {
        $rate1DeliveryDate = DateTime::createFromFormat(
            'Y/m/d',
            $rate1->estimatedDeliveryDate,
        );
        $rate2DeliveryDate = DateTime::createFromFormat(
            'Y/m/d',
            $rate2->estimatedDeliveryDate,
        );

        if ($rate1DeliveryDate < $rate2DeliveryDate) {
            return $rate1;
        }

        if (
            $rate1DeliveryDate == $rate2DeliveryDate &&
            $rate1->estimatedDeliveryTime !== null &&
            $rate2->estimatedDeliveryTime !== null
        ) {
            return $this->_compareEstimatedDeliveryTimes($rate1, $rate2);
        }

        return $rate2;
    }

    /**
     * Compare two rates and return the one with the quicker estimated delivery time
     *
     * @param ShippingRate $rate1
     * @param ShippingRate $rate2
     * @return ShippingRate
     */
    private function _compareEstimatedDeliveryTimes(
        ShippingRate $rate1,
        ShippingRate $rate2,
    ): ShippingRate {
        $rate1DeliveryTime = $this->_parseDateTime(
            $rate1->estimatedDeliveryTime,
            $rate1->estimatedDeliveryDate,
        );
        $rate2DeliveryTime = $this->_parseDateTime(
            $rate2->estimatedDeliveryTime,
            $rate2->estimatedDeliveryDate,
        );

        if ($rate1DeliveryTime < $rate2DeliveryTime) {
            return $rate1;
        }

        return $rate2;
    }

    /**
     * Compare two rates and return the one with the fewer transit days
     *
     * @param ShippingRate $rate1
     * @param ShippingRate $rate2
     * @return ShippingRate
     */
    private function _compareTransitDays(
        ShippingRate $rate1,
        ShippingRate $rate2,
    ): ShippingRate {
        if ($rate1->transitDays < $rate2->transitDays) {
            return $rate1;
        }

        return $rate2;
    }

    /**
     * Parse a date and time string into a `DateTime` object.
     *
     * @param string $timeString
     * @param string $dateString
     * @return DateTime
     */
    private function _parseDateTime(
        string $timeString,
        string $dateString,
    ): DateTime {
        $timezoneString = '';
        if (strpos($timeString, ' ') !== false) {
            $timezoneString = substr($timeString, strpos($timeString, ' ') + 1);
            $timeString = substr($timeString, 0, strpos($timeString, ' '));
        }

        $dateTimeString = $dateString . ' ' . $timeString;
        $dateTime = DateTime::createFromFormat('Y/m/d H:i', $dateTimeString);

        if ($timezoneString) {
            $dateTime->setTimezone(new DateTimeZone('UTC'));
        }

        return $dateTime;
    }
}
