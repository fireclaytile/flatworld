<?php

use Codeception\Test\Unit;
use fireclaytile\flatworld\services\ShippingRates;
use fireclaytile\flatworld\models\ShippingRate;

class ShippingRatesTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * Test that getRates() returns an array of ShippingRate objects.
     */
    public function testGetRatesReturnsArrayOfShippingRates()
    {
        $ratesJson = '
            [
                {
                    "Type": "parcel",
                    "TransitDays": "4",
                    "Total": "5.6435",
                    "ServiceLevel": "USPS First Class",
                    "EstimatedDeliveryTime": null,
                    "EstimatedDeliveryDate": "2023/10/07",
                    "CarrierName": "United States Postal Service"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "2",
                    "Total": "48.906",
                    "ServiceLevel": "USPS Express",
                    "EstimatedDeliveryTime": "08:30 ET",
                    "EstimatedDeliveryDate": "2023/10/04",
                    "CarrierName": "United States Postal Service"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "55.68",
                    "ServiceLevel": "UPS Next Day Air",
                    "EstimatedDeliveryTime": "10:30 ET",
                    "EstimatedDeliveryDate": "2023/10/03",
                    "CarrierName": "UPS"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "274.67",
                    "ServiceLevel": "UPS Next Day Air Early AM",
                    "EstimatedDeliveryTime": "08:30 ET",
                    "EstimatedDeliveryDate": "2023/10/03",
                    "CarrierName": "UPS"
                }
            ]';

        $shippingRates = new ShippingRates($ratesJson);

        $this->assertIsArray($shippingRates->getRates());

        foreach ($shippingRates->getRates() as $rate) {
            $this->assertInstanceOf(ShippingRate::class, $rate);
        }
    }

    /**
     * Test that getCheapestRate() returns the cheapest ShippingRate object.
     */
    public function testGetCheapestRateReturnsCheapestShippingRate()
    {
        $ratesJson = '
            [
                {
                    "Type": "parcel",
                    "TransitDays": "4",
                    "Total": "5.6435",
                    "ServiceLevel": "USPS First Class",
                    "EstimatedDeliveryTime": null,
                    "EstimatedDeliveryDate": "2023/10/07",
                    "CarrierName": "United States Postal Service"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "2",
                    "Total": "48.906",
                    "ServiceLevel": "USPS Express",
                    "EstimatedDeliveryTime": "08:30 ET",
                    "EstimatedDeliveryDate": "2023/10/04",
                    "CarrierName": "United States Postal Service"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "55.68",
                    "ServiceLevel": "UPS Next Day Air",
                    "EstimatedDeliveryTime": "10:30 ET",
                    "EstimatedDeliveryDate": "2023/10/03",
                    "CarrierName": "UPS"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "274.67",
                    "ServiceLevel": "UPS Next Day Air Early AM",
                    "EstimatedDeliveryTime": "08:30 ET",
                    "EstimatedDeliveryDate": "2023/10/03",
                    "CarrierName": "UPS"
                }
            ]';

        $shippingRates = new ShippingRates($ratesJson);

        $cheapestRate = $shippingRates->getCheapestRate();

        $this->assertInstanceOf(ShippingRate::class, $cheapestRate);
        $this->assertEquals('USPS First Class', $cheapestRate->serviceLevel);
        $this->assertEquals(
            'USPS_FIRST_CLASS',
            $cheapestRate->getServiceHandle(),
        );
        $this->assertEquals(4, $cheapestRate->transitDays);
        $this->assertEquals(5.6435, $cheapestRate->total);
        $this->assertNull($cheapestRate->estimatedDeliveryTime);
        $this->assertEquals('2023/10/07', $cheapestRate->estimatedDeliveryDate);
        $this->assertEquals(
            'United States Postal Service',
            $cheapestRate->carrierName,
        );
        $this->assertEquals('parcel', $cheapestRate->type);
    }

    /**
     * Test that getFastestShippingRate() returns the fastest ShippingRate option.
     */
    public function testGetFastestShippingRateReturnsFastestShippingRate()
    {
        $ratesJson = '
            [
                {
                    "Type": "parcel",
                    "TransitDays": "4",
                    "Total": "5.6435",
                    "ServiceLevel": "USPS First Class",
                    "EstimatedDeliveryTime": null,
                    "EstimatedDeliveryDate": "2023/10/07",
                    "CarrierName": "United States Postal Service"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "2",
                    "Total": "48.906",
                    "ServiceLevel": "USPS Express",
                    "EstimatedDeliveryTime": "08:30 ET",
                    "EstimatedDeliveryDate": "2023/10/04",
                    "CarrierName": "United States Postal Service"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "55.68",
                    "ServiceLevel": "UPS Next Day Air",
                    "EstimatedDeliveryTime": "10:30 ET",
                    "EstimatedDeliveryDate": "2023/10/03",
                    "CarrierName": "UPS"
                },
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "274.67",
                    "ServiceLevel": "UPS Next Day Air Early AM",
                    "EstimatedDeliveryTime": "08:30 ET",
                    "EstimatedDeliveryDate": "2023/10/03",
                    "CarrierName": "UPS"
                }
            ]';

        $shippingRates = new ShippingRates($ratesJson);

        $fastestShippingRate = $shippingRates->getFastestRate();

        $this->assertInstanceOf(ShippingRate::class, $fastestShippingRate);
        $this->assertEquals(
            'UPS Next Day Air Early AM',
            $fastestShippingRate->serviceLevel,
        );
        $this->assertEquals(
            'UPS_NEXT_DAY_AIR_EARLY_AM',
            $fastestShippingRate->getServiceHandle(),
        );
        $this->assertEquals(1, $fastestShippingRate->transitDays);
        $this->assertEquals(274.67, $fastestShippingRate->total);
        $this->assertEquals(
            '08:30 ET',
            $fastestShippingRate->estimatedDeliveryTime,
        );
        $this->assertEquals(
            '2023/10/03',
            $fastestShippingRate->estimatedDeliveryDate,
        );
        $this->assertEquals('UPS', $fastestShippingRate->carrierName);
        $this->assertEquals('parcel', $fastestShippingRate->type);
    }

    /**
     * Test that the getArrivalEstimationString method returns the correct string.
     */
    public function testGetArrivalEstimationStringReturnsCorrectString()
    {
        $ratesJson = '
            [
                {
                    "Type": "parcel",
                    "TransitDays": "1",
                    "Total": "5.6435",
                    "ServiceLevel": "USPS First Class",
                    "EstimatedDeliveryTime": null,
                    "EstimatedDeliveryDate": "2023/10/07",
                    "CarrierName": "United States Postal Service"
                }
            ]';

        $shippingRates = new ShippingRates($ratesJson);

        $expectedString = '1-3 days';

        $this->assertEquals(
            $expectedString,
            $shippingRates->getRates()[0]->getArrivalEstimationString(),
        );
    }

    /**
     * Test that the getArrivalEstimationString method returns an empty string if transit days is 0.
     */
    public function testGetArrivalEstimationStringReturnsEmptyStringIfTransitDaysIsZero()
    {
        $ratesJson = '
        [
            {
                "Type": "parcel",
                "TransitDays": "0",
                "Total": "5.6435",
                "ServiceLevel": "USPS First Class",
                "EstimatedDeliveryTime": null,
                "EstimatedDeliveryDate": "2023/10/07",
                "CarrierName": "United States Postal Service"
            }
        ]';

        $shippingRates = new ShippingRates($ratesJson);

        $this->assertEquals(
            '',
            $shippingRates->getRates()[0]->getArrivalEstimationString(),
        );
    }

    /**
     * Test that the getArrivalEstimationString method returns the correct string for a transit time of 21 days or more.
     */
    public function testGetArrivalEstimationStringReturnsCorrectStringForTransitTimeOf21DaysOrMore()
    {
        $ratesJson = '
        [
            {
                "Type": "parcel",
                "TransitDays": "25",
                "Total": "5.6435",
                "ServiceLevel": "USPS First Class",
                "EstimatedDeliveryTime": null,
                "EstimatedDeliveryDate": "2023/10/07",
                "CarrierName": "United States Postal Service"
            }
        ]';

        $shippingRates = new ShippingRates($ratesJson);

        $expectedString = '21 days or more';

        $this->assertEquals(
            $expectedString,
            $shippingRates->getRates()[0]->getArrivalEstimationString(),
        );
    }

    /**
     * Test that getRates only includes rates from allowed carriers
     */
    public function testSetRatesWithAllowedCarriers()
    {
        $ratesJson = '
        [
            {
                "Type": "parcel",
                "TransitDays": "25",
                "Total": "5.6435",
                "ServiceLevel": "USPS First Class",
                "EstimatedDeliveryTime": null,
                "EstimatedDeliveryDate": "2023/10/07",
                "CarrierName": "United States Postal Service"
            },
            {
                "Type": "parcel",
                "TransitDays": "1",
                "Total": "55.68",
                "ServiceLevel": "UPS Next Day Air",
                "EstimatedDeliveryTime": "10:30 ET",
                "EstimatedDeliveryDate": "2023/10/03",
                "CarrierName": "UPS"
            }
        ]';

        // Create an array of allowed carrier servicess
        $allowedCarrierServices = [
            'ABF_FREIGHT' => 'ABF Freight',
            'DEPENDABLE_HIGHWAY_EXPRESS' => 'Dependable Highway Express',
            'FEDEX_FREIGHT_PRIORITY' => 'FedEx Freight Priority',
            'OAK_HARBOR' => 'Oak Harbor',
            'OLD_DOMINION_FREIGHT_LINE' => 'Old Dominion Freight Line',
            'R_&_L_CARRIERS' => 'R & L Carriers',
            'FEDEX_GROUND' => 'FedEx Ground',
            'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
            'FEDEX_2DAY' => 'FedEx 2Day',
            'FEDEX_2DAY_A.M.' => 'FedEx 2Day A.M.',
            'FEDEX_STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'FEDEX_PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'FEDEX_FIRST_OVERNIGHT' => 'FedEx First Overnight',
            'USPS_FIRST_CLASS' => 'USPS First Class',
            'USPS_GROUNDADVANTAGE' => 'USPS GroundAdvantage',
            'USPS_PARCEL_SELECT' => 'USPS Parcel Select',
            'USPS_EXPRESS' => 'USPS Express',
            'USPS_PRIORITY' => 'USPS Priority',
            'USPS_FLAT_RATE' => 'USPS Flat Rate',
        ];

        // Create a ShippingRates object
        $shippingRates = new ShippingRates($ratesJson, $allowedCarrierServices);

        // Get the rates
        $rates = $shippingRates->getRates();
        codecept_debug(print_r($rates, true));

        // Check that the rates only include the allowed carriers
        foreach ($rates as $rate) {
            $this->assertContains($rate->serviceLevel, $allowedCarrierServices);
        }

        // Check that the only rate is the expected one.
        $expectedString = 'USPS First Class';
        $this->assertEquals($expectedString, $rates[0]->serviceLevel);
    }
}
