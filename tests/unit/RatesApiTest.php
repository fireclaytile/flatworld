<?php

namespace fireclaytile\flatworld\tests\unit;

use Craft;
use Exception;
use UnitTester;
use craft\helpers\Json;
use craft\helpers\App;
use verbb\postie\Postie;
use Codeception\Test\Unit;
use fireclaytile\flatworld\Flatworld;
use fireclaytile\flatworld\variables\FlatworldVariable;
use fireclaytile\flatworld\services\RatesApi;
use fireclaytile\flatworld\services\salesforce\SalesforceRestConnection;
use fireclaytile\flatworld\services\salesforce\models\ShippingRequest;
use fireclaytile\flatworld\services\salesforce\models\LineItem;

class RatesApiTest extends Unit
{
    protected RatesApi $ratesApiService;

    /**
     * @var array
     */
    public array $mockServiceList;

    /**
     * Instance of the SalesforceRestConnection class.
     *
     * @var mixed
     */
    public $sf;

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
        $this->ratesApiService = Flatworld::getInstance()->ratesApi;
    }

    public function testGetRatesWithValidRequest()
    {
        // Prepare the sample shipping request
        $shippingRequest = new ShippingRequest('11111', true, [
            new LineItem('12345', 25),
        ]);

        // Call the getRates function with the sample shipping request
        $this->salesforceConnect();
        codecept_debug($this->sf);
        $rates = $this->ratesApiService->getRates($shippingRequest, $this->sf);
        codecept_debug($rates);

        // Assert that the returned rates are in the expected JSON format
        $expectedRates =
            '[{"AccessorialsCharge":"184.6","CarrierId":"13404","CarrierMaximumCoverage":"9500","CarrierName":"Dependable Highway Express","Days":"1","Direct":"D","FuelSurcharge":"20.3","SCAC":"DPHE","SubTotal":"250.53","Total":"583.19","TransitDays":"1","Type":"ltl"},{"AccessorialsCharge":"179.7","CarrierId":"13410","CarrierMaximumCoverage":"4465","CarrierName":"Oak Harbor","Days":"1","Direct":"D","FuelSurcharge":"20.3","GuaranteedServiceCharge":"85","SCAC":"OAKH","SubTotal":"277.74","Total":"616.58","TransitDays":"1","Type":"ltl"},{"AccessorialsCharge":"53","CarrierId":"13427","CarrierMaximumCoverage":"4370","CarrierName":"R %20amp; L Carriers","Days":"1","Direct":"D","FuelSurcharge":"20.3","GuaranteedServiceCharge":"76.19","SCAC":"RLCA","SubTotal":"380.96","Total":"613.55","TransitDays":"1","Type":"ltl"},{"AccessorialsCharge":"112.6","CarrierId":"13416","CarrierMaximumCoverage":"9500","CarrierName":"Old Dominion Freight Line","Days":"1","Direct":"D","FuelSurcharge":"20.3","GuaranteedServiceCharge":"105.09","SCAC":"ODFL","SubTotal":"420.37","Total":"741.97","TransitDays":"1","Type":"ltl"},{"AccessorialsCharge":"86.6","CarrierId":"13406","CarrierMaximumCoverage":"5700","CarrierName":"ABF Freight","Days":"1","Direct":"D","FuelSurcharge":"20.3","GuaranteedServiceCharge":"200","SCAC":"ABFS","SubTotal":"788.69","Total":"1242.47","TransitDays":"1","Type":"ltl"},{"AccessorialsCharge":"255.58","CarrierId":"9755","CarrierMaximumCoverage":"5700","CarrierName":"FedEx Freight Priority","Days":"1","Direct":"D","FuelSurcharge":"23.3","GuaranteedServiceCharge":"118.65","SCAC":"FXFE","SubTotal":"248.67","Total":"674.63","TransitDays":"1","Type":"ltl"}]';
        $this->assertJsonStringEqualsJsonString($expectedRates, $rates);
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
            'weightThreshold' => '150',
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

    private function salesforceConnect(): bool
    {
        // Create connection to Salesforce
        try {
            $apiConsumerKey = $this->flatworld->getSetting('apiConsumerKey');
            $apiConsumerSecret = $this->flatworld->getSetting(
                'apiConsumerSecret',
            );
            $apiUsername = $this->flatworld->getSetting('apiUsername');
            $apiPassword = $this->flatworld->getSetting('apiPassword');
            $apiUrl = $this->flatworld->getSetting('apiUrl');

            $this->sf = new SalesforceRestConnection(
                $apiConsumerKey,
                $apiConsumerSecret,
                $apiUsername,
                $apiPassword,
                $apiUrl,
            );

            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }
}
