<?php

namespace fireclaytile\flatworld\tests\unit;

use Codeception\Test\Unit;
use Craft;
use UnitTester;
use craft\helpers\App;
use fireclaytile\flatworld\Flatworld as FlatworldPlugin;
use fireclaytile\flatworld\models\LineItem;
use fireclaytile\flatworld\models\ShippingRequest;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\services\RatesApi;
use fireclaytile\flatworld\variables\FlatworldVariable;
use verbb\postie\Postie;

class RatesApiTest extends Unit
{
    protected RatesApi $ratesApiService;

    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var FlatworldProvider
     */
    protected FlatworldProvider $flatworld;

    /**
     * @var array
     */
    public array $mockServiceList;

    protected function _before(): void
    {
        parent::_before();

        Craft::$app->setEdition(Craft::Pro);

        $this->flatworldVariable = new FlatworldVariable();

        $this->flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');

        $this->prepareMockData();

        // Create an instance of the RatesApi class
        $this->ratesApiService = FlatworldPlugin::getInstance()->ratesApi;
    }

    public function testGetRatesWithValidRequest()
    {
        // Prepare the sample shipping request
        $shippingRequest = new ShippingRequest('46239', true, 'Sample', []);
        $shippingRequest->addLineItem(new LineItem('01t8000000336tr', 1));

        $this->ratesApiService = new RatesApi($this->flatworld->settings);

        $rates = $this->ratesApiService->getRates($shippingRequest);
        codecept_debug($rates);

        $this->assertNotNull($rates);
        $this->assertJson($rates);
    }

    protected function prepareMockData(): void
    {
        $this->mockServiceList = [
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
            'UPS_2ND_DAY_AIR' => 'UPS 2nd Day Air',
            'UPS_SECOND_DAY_AIR_AM' => 'UPS Second Day Air AM',
            'UPS_3_DAY_SELECT' => 'UPS 3 Day Select',
            'UPS_GROUND' => 'UPS Ground',
            'UPS_NEXT_DAY_AIR' => 'UPS Next Day Air',
            'UPS_NEXT_DAY_AIR_SAVER' => 'UPS Next Day Air Saver',
            'UPS_NEXT_DAY_AIR_EARLY_AM' => 'UPS Next Day Air Early AM',
            'USPS_FIRST_CLASS' => 'USPS First Class',
            'USPS_GROUNDADVANTAGE' => 'USPS GroundAdvantage',
            'USPS_PARCEL_SELECT' => 'USPS Parcel Select',
            'USPS_EXPRESS' => 'USPS Express',
            'USPS_PRIORITY' => 'USPS Priority',
            'USPS_FLAT_RATE' => 'USPS Flat Rate',
            'FLAT_RATE_SHIPPING' => 'Flat Rate Shipping',
            'TRADE_CUSTOMER_SHIPPING' => 'Trade Customer Shipping',
        ];

        $this->flatworld->settings = [
            'apiUrl' => App::env('SALESFORCE_API_URL'),
            'apiUsername' => App::env('SALESFORCE_USERNAME'),
            'apiPassword' => App::env('SALESFORCE_PASSWORD'),
            'apiConsumerKey' => App::env('SALESFORCE_CONSUMER_KEY'),
            'apiConsumerSecret' => App::env('SALESFORCE_CONSUMER_SECRET'),
            'enableSalesforceApi' => App::env('SALESFORCE_CONNECT'),
            'enableSalesforceSandbox' => App::env('SALESFORCE_SANDBOX'),
            'totalMaxWeight' => '39750',
            'weightLimitMessage' =>
                'Shipping weight limit reached. Please contact Fireclay Tile Salesperson.',
            'weightPerSquareFoot' => [
                [
                    0 => 'tile',
                    1 => '',
                    2 => '4.5',
                ],
                [
                    0 => 'quickShipTile',
                    1 => '',
                    2 => '4.5',
                ],
                [
                    0 => 'brick',
                    1 => '',
                    2 => '5',
                ],
                [
                    0 => 'quickShipBrick',
                    1 => '',
                    2 => '5',
                ],
                [
                    0 => 'glass',
                    1 => '',
                    2 => '3',
                ],
                [
                    0 => 'quickShipGlass',
                    1 => '',
                    2 => '3',
                ],
                [
                    0 => 'quickShipEssentials',
                    1 => '',
                    2 => '3.4',
                ],
                [
                    0 => 'quickShipSeconds',
                    1 => 'tile',
                    2 => '3.4',
                ],
                [
                    0 => 'quickShipSeconds',
                    1 => 'brick',
                    2 => '5',
                ],
                [
                    0 => 'quickShipSeconds',
                    1 => 'glass',
                    2 => '3',
                ],
            ],
            'displayDebugMessages' => '1',
            'flatRateCarrierName' => 'USPS Flat Rate',
            'flatRateCarrierCost' => '8.0',
            'carrierClassOfServices' => $this->mockServiceList,
        ];
    }
}
