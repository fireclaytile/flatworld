<?php

namespace fireclaytile\flatworld\tests\unit;

use Craft;
use Exception;
use UnitTester;
use craft\helpers\App;
use craft\helpers\Json;
use verbb\postie\Postie;
use Codeception\Test\Unit;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use craft\helpers\ArrayHelper;
use yii\base\InvalidConfigException;
use fireclaytile\flatworld\Flatworld as FlatworldPlugin;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\services\Logger;
use fireclaytile\flatworld\services\Rates as RatesService;
use fireclaytile\flatworld\variables\FlatworldVariable;

class FlatworldProviderTest extends Unit
{
    /**
     * @var boolean
     */
    private bool $_loggingEnabled;

    /**
     * @var MockOrder $order
     */
    protected MockOrder $order;

    /**
     * @var FlatworldProvider
     */
    protected FlatworldProvider $flatworld;

    // /**
    //  * @var RatesService
    //  */
    // protected RatesService $ratesService;

    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var array
     */
    public array $mockServiceList;

    /**
     * @return void
     */
    protected function _before(): void
    {
        parent::_before();

        Craft::$app->setEdition(Craft::Pro);

        $this->flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');

        $this->_loggingEnabled = true;

        $this->prepareMockData();
    }

    /**
     * @return void
     */
    public function testConfigOptionsSpecificToFlatworldAreValidAndCorrect(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $config = Craft::$app->getConfig()->getConfigFromFile('postie');

        $this->assertIsArray($config);
        $this->assertNotEmpty($config);
        $this->assertArrayHasKey('providers', $config);

        $this->assertIsArray($config['providers']);
        $this->assertNotEmpty($config['providers']);
        $this->assertArrayHasKey('flatworld', $config['providers']);

        $this->assertIsArray($config['providers']['flatworld']);
        $this->assertNotEmpty($config['providers']['flatworld']);

        $flatworld = $config['providers']['flatworld'];

        $this->assertIsArray($flatworld);
        $this->assertNotEmpty($flatworld);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     */
    public function testSettingsSpecificToFlatworldAreValidAndCorrect(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $this->assertNotEmpty($this->flatworld->getSetting('apiUsername'));
        $this->assertSame(
            App::env('SALESFORCE_USERNAME'),
            $this->flatworld->getSetting('apiUsername'),
        );

        $this->assertNotEmpty($this->flatworld->getSetting('apiPassword'));
        $this->assertSame(
            App::env('SALESFORCE_PASSWORD'),
            $this->flatworld->getSetting('apiPassword'),
        );

        $this->assertNotEmpty($this->flatworld->getSetting('apiUrl'));
        $this->assertSame(
            App::env('SALESFORCE_API_URL'),
            $this->flatworld->getSetting('apiUrl'),
        );

        $this->assertNotEmpty($this->flatworld->getSetting('apiConsumerKey'));
        $this->assertSame(
            App::env('SALESFORCE_CONSUMER_KEY'),
            $this->flatworld->getSetting('apiConsumerKey'),
        );

        $this->assertNotEmpty(
            $this->flatworld->getSetting('apiConsumerSecret'),
        );
        $this->assertSame(
            App::env('SALESFORCE_CONSUMER_SECRET'),
            $this->flatworld->getSetting('apiConsumerSecret'),
        );

        $this->assertNotEmpty(
            $this->flatworld->getSetting('enableSalesforceApi'),
        );
        $this->assertSame(
            App::env('SALESFORCE_CONNECT'),
            $this->flatworld->getSetting('enableSalesforceApi'),
        );

        $this->assertNotEmpty(
            $this->flatworld->getSetting('enableSalesforceSandbox'),
        );
        $this->assertSame(
            App::env('SALESFORCE_SANDBOX'),
            $this->flatworld->getSetting('enableSalesforceSandbox'),
        );

        $this->assertNotEmpty($this->flatworld->getSetting('totalMaxWeight'));
        $this->assertSame(
            '39750',
            $this->flatworld->getSetting('totalMaxWeight'),
        );

        $this->assertNotEmpty(
            $this->flatworld->getSetting('weightLimitMessage'),
        );
        $this->assertSame(
            'Shipping weight limit reached. Please contact Fireclay Tile Salesperson.',
            $this->flatworld->getSetting('weightLimitMessage'),
        );

        $weightPerSquareFoot = $this->flatworld->getSetting(
            'weightPerSquareFoot',
        );

        $this->assertNotEmpty($weightPerSquareFoot);
        $this->assertIsArray($weightPerSquareFoot);

        $this->assertNotEmpty($weightPerSquareFoot[0][0]);
        $this->assertSame('tile', $weightPerSquareFoot[0][0]);

        $this->assertNotEmpty($weightPerSquareFoot[0][2]);
        $this->assertSame('4.5', $weightPerSquareFoot[0][2]);

        $this->assertNotEmpty(
            $this->flatworld->getSetting('displayDebugMessages'),
        );
        $this->assertTrue(
            (bool) $this->flatworld->getSetting('displayDebugMessages'),
        );

        $this->assertNotEmpty(
            $this->flatworld->getSetting('flatRateCarrierCost'),
        );
        $this->assertSame(
            '8.0',
            $this->flatworld->getSetting('flatRateCarrierCost'),
        );

        $carrierClassOfServices = $this->flatworld->getSetting(
            'carrierClassOfServices',
        );

        $this->assertIsArray($carrierClassOfServices);
        $this->assertNotEmpty($carrierClassOfServices);
        $this->assertSame($this->mockServiceList, $carrierClassOfServices);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function testSettingsTemplateHasFlatworldSpecificFields(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $settingsHtml = $this->flatworld->getSettingsHtml();

        $this->assertNotEmpty($settingsHtml);
        $this->assertStringContainsString('apiUrl-label', $settingsHtml);
        $this->assertStringContainsString('apiUsername-label', $settingsHtml);
        $this->assertStringContainsString('apiPassword-label', $settingsHtml);
        $this->assertStringContainsString(
            'apiConsumerKey-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'apiConsumerSecret-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'enableSalesforceApi-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'enableSalesforceSandbox-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'totalMaxWeight-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'weightLimitMessage-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'weightPerSquareFoot-heading-1',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'weightPerSquareFoot-heading-2',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'weightPerSquareFoot-heading-3',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'displayDebugMessages-label',
            $settingsHtml,
        );
        $this->assertStringContainsString(
            'flatRateCarrierCost-label',
            $settingsHtml,
        );

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     */
    public function testDisplayNameReturnsAValidString(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $displayName = $this->flatworld->displayName();

        $this->assertNotEmpty($displayName);
        $this->assertSame('Flatworld', $displayName);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     */
    public function testIconUrlReturnsAValidString(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $iconUrl = $this->flatworld->getIconUrl();

        $this->assertNotEmpty($iconUrl);
        $this->assertRegExp(
            '/https:\/\/flatworld\.test:80\/cpresources\/[a-f0-9]{8}\/Flatworld\.svg\?v=\d+/',
            $iconUrl,
        );

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     */
    public function testServiceListReturnsAValidArrayOfServices(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $serviceList = $this->flatworld->getServiceList();

        $this->assertIsArray($serviceList);
        $this->assertNotEmpty($serviceList);
        $this->assertSame($this->mockServiceList, $serviceList);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     */
    public function testDisplayDebugMessageDoesNotWriteToLogFileWhenDisabled(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        // Explicitly setting display debug messages to false
        $this->_loggingEnabled = false;

        $this->assertIsBool(
            $this->_logMessage(
                __METHOD__,
                'This was not written to a log file!',
            ),
        );
        $this->assertFalse(
            $this->_logMessage(
                __METHOD__,
                'This was not written to a log file!',
            ),
        );

        $this->_loggingEnabled = true;
        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     */
    public function testDisplayDebugMessageWritesToLogFileWhenEnabled(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        // Explicitly setting display debug messages to true
        $this->_loggingEnabled = true;

        $this->assertIsBool(
            $this->_logMessage(__METHOD__, 'This was written to a log file!'),
        );
        $this->assertTrue(
            $this->_logMessage(__METHOD__, 'This was written to a log file!'),
        );

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testFetchShippingRatesReturnsEmptyRatesWhenAnOrderHasNoLineItems(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $mockOrder = $this->createMockOrder(false, false);

        $rates = $this->flatworld->fetchShippingRates($mockOrder);

        $this->assertIsArray($rates);
        $this->assertEmpty($rates);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testFetchShippingRatesReturnsEmptyRatesWhenAnOrderHasNoShippingAddress(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $mockOrder = $this->createMockOrder(true, false);

        $rates = $this->flatworld->fetchShippingRates($mockOrder);

        $this->assertIsArray($rates);
        $this->assertEmpty($rates);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * Test fetchShippingRates method with a valid order. Currently uses fake rates data.
     * @return void
     * @throws Exception
     */
    public function testFetchShippingRatesReturnsParcelResults(): void
    {
        $this->_logMessage(__METHOD__, 'running...');

        $mockOrder = $this->createMockOrder();

        $rates = $this->flatworld->fetchShippingRates($mockOrder);
        $this->_logMessage(__METHOD__, 'rates: ' . print_r($rates, true));

        $this->assertIsArray($rates);
        $this->assertNotEmpty($rates);

        $this->_logMessage(__METHOD__, 'done...');
    }

    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $message Message to log
     * @param string $method Method that called this function
     * @param string|null $uniqueId Unique ID for this call
     * @return boolean
     */
    private function _logMessage(string $method, string $message): bool
    {
        if (!$this->_loggingEnabled) {
            return false;
        }

        $msg = "{$method} :: {$message}";

        return Logger::logMessage($msg, true);
    }

    /**
     * @return void
     */
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

    /**
     * @param bool $includeLineItem
     * @param bool $includeAddresses
     * @return MockOrder
     */
    public function createMockOrder(
        bool $includeLineItem = true,
        bool $includeAddresses = true,
    ): MockOrder {
        $order = new MockOrder();
        $order->id = 1;
        $order->currency = 'USD';
        $order->email = 'joe@bloggs.com';
        $order->number = md5(uniqid(mt_rand(), true));

        $userGroup1 = new MockUserGroup();
        $userGroup1->id = 1;
        $userGroup1->name = 'Mock Group 2';
        $userGroup1->handle = 'mockGroup2';

        $user = new MockUser();
        $user->fullName = 'Joe Bloggs';
        $user->email = 'joe@bloggs.com';

        $user->setGroups([$userGroup1]);

        $order->user = $user;

        // Explicitly setting line items
        if ($includeLineItem) {
            $categoryGroup1 = new MockCategoryGroup();
            $categoryGroup1->id = 1;
            $categoryGroup1->title = 'Product Lines Category';
            $categoryGroup1->slug = 'productLines';

            $category1 = new MockCategory();
            $category1->id = 1;
            $category1->groupId = 1;
            $category1->group = $categoryGroup1;
            $category1->title = 'Tile';
            $category1->slug = 'tile';

            $productType1 = new MockProductType();
            $productType1->id = 1;
            $productType1->name = 'Test Product Type 1';
            $productType1->handle = 'merchandise';

            $productType2 = new MockProductType();
            $productType2->id = 2;
            $productType2->name = 'Test Product Type 2';
            $productType2->handle = 'addons';

            $productType3 = new MockProductType();
            $productType3->id = 3;
            $productType3->name = 'Test Product Type 3';
            $productType3->handle = 'tile';

            $productType4 = new MockProductType();
            $productType4->id = 4;
            $productType4->name = 'Test Product Type 4';
            $productType4->handle = 'brick';

            $productType5 = new MockProductType();
            $productType5->id = 5;
            $productType5->name = 'Test Product Type 5';
            $productType5->handle = 'glass';

            $productType6 = new MockProductType();
            $productType6->id = 6;
            $productType6->name = 'Test Product Type 6';
            $productType6->handle = 'tile';

            $product1 = new MockProduct();
            $product1->id = 1;
            $product1->shippingCategoryId = 1;
            $product1->name = 'Test Product 1';
            $product1->typeId = 1;
            $product1->type = $productType1;
            $product1->colorProductLinesCategory = [$category1];
            $product1->defaultSku = 'test-product-1';

            $product2 = new MockProduct();
            $product2->id = 2;
            $product2->shippingCategoryId = 1;
            $product2->name = 'Test Product 2';
            $product2->typeId = 1;
            $product2->type = $productType2;
            $product2->defaultSku = 'test-product-2';

            $product3 = new MockProduct();
            $product3->id = 3;
            $product3->shippingCategoryId = 1;
            $product3->name = 'Test Product 3';
            $product3->typeId = 3;
            $product3->type = $productType3;
            $product3->defaultSku = 'test-product-3';

            $product4 = new MockProduct();
            $product4->id = 4;
            $product4->shippingCategoryId = 1;
            $product4->name = 'Test Product 4';
            $product4->typeId = 4;
            $product4->type = $productType4;
            $product4->defaultSku = 'test-product-4';

            $product5 = new MockProduct();
            $product5->id = 5;
            $product5->shippingCategoryId = 1;
            $product5->name = 'Test Product 5';
            $product5->typeId = 5;
            $product5->type = $productType5;
            $product5->defaultSku = 'test-product-5';

            $product6 = new MockProduct();
            $product6->id = 6;
            $product6->shippingCategoryId = 1;
            $product6->name = 'Test Product 6';
            $product6->typeId = 6;
            $product6->type = $productType6;
            $product6->defaultSku = 'test-product-6';

            $variant1 = new MockVariant();
            $variant1->id = 1;
            $variant1->minQty = 1;
            $variant1->maxQty = 99;
            $variant1->stock = 99;
            $variant1->price = 100;
            $variant1->width = 100;
            $variant1->length = 100;
            $variant1->height = 100;
            $variant1->weight = 0.712;
            $variant1->isDefault = true;
            $variant1->product = $product1;
            $variant1->hasUnlimitedStock = false;
            $variant1->url = 'https://www.domain.com';

            $variant2 = new MockVariant();
            $variant2->id = 2;
            $variant2->minQty = 1;
            $variant2->maxQty = 99;
            $variant2->stock = 99;
            $variant2->price = 100;
            $variant2->width = 100;
            $variant2->length = 100;
            $variant2->height = 100;
            $variant2->weight = 0.712;
            $variant2->isDefault = true;
            $variant2->product = $product2;
            $variant2->hasUnlimitedStock = false;
            $variant2->url = 'https://www.domain.com';

            $variant3 = new MockVariant();
            $variant3->id = 3;
            $variant3->minQty = 1;
            $variant3->maxQty = 99;
            $variant3->stock = 99;
            $variant3->price = 100;
            $variant3->width = 100;
            $variant3->length = 100;
            $variant3->height = 100;
            $variant3->weight = 0.712;
            $variant3->isDefault = true;
            $variant3->product = $product3;
            $variant3->hasUnlimitedStock = false;
            $variant3->url = 'https://www.domain.com';

            $variant4 = new MockVariant();
            $variant4->id = 4;
            $variant4->minQty = 1;
            $variant4->maxQty = 99;
            $variant4->stock = 99;
            $variant4->price = 100;
            $variant4->width = 100;
            $variant4->length = 100;
            $variant4->height = 100;
            $variant4->weight = 0.712;
            $variant4->isDefault = true;
            $variant4->product = $product4;
            $variant4->hasUnlimitedStock = false;
            $variant4->url = 'https://www.domain.com';

            $variant5 = new MockVariant();
            $variant5->id = 5;
            $variant5->minQty = 1;
            $variant5->maxQty = 99;
            $variant5->stock = 99;
            $variant5->price = 100;
            $variant5->width = 100;
            $variant5->length = 100;
            $variant5->height = 100;
            $variant5->weight = 0.712;
            $variant5->isDefault = true;
            $variant5->product = $product5;
            $variant5->hasUnlimitedStock = false;
            $variant5->url = 'https://www.domain.com';

            $variant6 = new MockVariant();
            $variant6->id = 6;
            $variant6->minQty = 1;
            $variant6->maxQty = 99;
            $variant6->stock = 99;
            $variant6->price = 100;
            $variant6->width = 100;
            $variant6->length = 100;
            $variant6->height = 100;
            $variant6->weight = 0.712;
            $variant6->isDefault = true;
            $variant6->product = $product6;
            $variant6->hasUnlimitedStock = false;
            $variant6->url = 'https://www.domain.com';

            $product1->variants = [$variant1];

            $product2->variants = [$variant2];

            $product3->variants = [$variant3];

            $product4->variants = [$variant4];

            $product5->variants = [$variant5];

            $product6->variants = [$variant6];

            $lineItem1 = new MockLineItem();
            $lineItem1->id = 1;
            $lineItem1->qty = 1;
            $lineItem1->price = 100;
            $lineItem1->width = 100;
            $lineItem1->length = 100;
            $lineItem1->height = 100;
            $lineItem1->weight = 0.712;
            $lineItem1->salePrice = 90;
            $lineItem1->orderId = $order->id;
            $lineItem1->purchasable = $variant1;
            $lineItem1->sku = 'test-line-item-1';
            $lineItem1->description = 'Test Line Item 1';

            $lineItem2 = new MockLineItem();
            $lineItem2->id = 2;
            $lineItem2->qty = 1;
            $lineItem2->price = 100;
            $lineItem2->width = 100;
            $lineItem2->length = 100;
            $lineItem2->height = 100;
            $lineItem2->weight = 0.712;
            $lineItem2->salePrice = 90;
            $lineItem2->orderId = $order->id;
            $lineItem2->purchasable = $variant2;
            $lineItem2->sku = 'test-line-item-2';
            $lineItem2->description = 'Test Line Item 2';

            $lineItem3 = new MockLineItem();
            $lineItem3->id = 3;
            $lineItem3->qty = 1;
            $lineItem3->price = 100;
            $lineItem3->width = 100;
            $lineItem3->length = 100;
            $lineItem3->height = 100;
            $lineItem3->weight = 0.712;
            $lineItem3->salePrice = 90;
            $lineItem3->orderId = $order->id;
            $lineItem3->purchasable = $variant3;
            $lineItem3->sku = 'test-line-item-3';
            $lineItem3->description = 'Test Line Item 3';

            $lineItem4 = new MockLineItem();
            $lineItem4->id = 4;
            $lineItem4->qty = 1;
            $lineItem4->price = 100;
            $lineItem4->width = 100;
            $lineItem4->length = 100;
            $lineItem4->height = 100;
            $lineItem4->weight = 0.712;
            $lineItem4->salePrice = 90;
            $lineItem4->orderId = $order->id;
            $lineItem4->purchasable = $variant4;
            $lineItem4->sku = 'test-line-item-4';
            $lineItem4->description = 'Test Line Item 4';

            $lineItem5 = new MockLineItem();
            $lineItem5->id = 5;
            $lineItem5->qty = 1;
            $lineItem5->price = 100;
            $lineItem5->width = 100;
            $lineItem5->length = 100;
            $lineItem5->height = 100;
            $lineItem5->weight = 0.712;
            $lineItem5->salePrice = 90;
            $lineItem5->orderId = $order->id;
            $lineItem5->purchasable = $variant5;
            $lineItem5->sku = 'test-line-item-5';
            $lineItem5->description = 'Test Line Item 5';

            $lineItem6 = new MockLineItem();
            $lineItem6->id = 6;
            $lineItem6->qty = 1;
            $lineItem6->options = [
                'sample' => true,
            ];
            $lineItem6->price = 100;
            $lineItem6->width = 100;
            $lineItem6->length = 100;
            $lineItem6->height = 100;
            $lineItem6->weight = 0.712;
            $lineItem6->salePrice = 90;
            $lineItem6->orderId = $order->id;
            $lineItem6->purchasable = $variant6;
            $lineItem6->sku = 'test-line-item-6';
            $lineItem6->description = 'Test Line Item 6';

            $order->lineItems = [
                $lineItem1,
                $lineItem2,
                $lineItem3,
                $lineItem4,
                $lineItem5,
                $lineItem6,
            ];
        } else {
            $order->lineItems = [];
        }

        // Explicitly setting shipping and billing addresses
        if ($includeAddresses) {
            $address = new MockAddress();
            $address->id = 1;
            $address->title = 'Mr';
            $address->firstName = 'Joe';
            $address->lastName = 'Bloggs';
            $address->fullName = 'Mr Joe Bloggs';
            $address->email = 'joe@bloggs.com';
            $address->address1 = '132 My Street';
            $address->address2 = '';
            $address->address3 = '';
            $address->city = 'New York';
            $address->postalCode = '10001';
            $address->phone = '';
            $address->stateName = 'New York';
            $address->countryId = 236;
            $address->countryIso = 'US';
            $address->state = new MockAddressState();
            $address->state->id = 54;
            $address->state->abbreviation = 'NY';
            $address->stateId = $address->state->id;
            $address->businessName = '';

            $order->billingAddress = $address;
            $order->shippingAddress = $address;
            $order->estimatedBillingAddress = $address;
            $order->estimatedShippingAddress = $address;
        }

        return $order;
    }
}

// Once the structure matches a real Commerce Order|Product|ProductType|Variant|LineItem|Address class, we don't really care about anything else!
class MockOrder
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $email;

    /**
     * @var string
     */
    public string $number;

    /**
     * @var string
     */
    public string $currency;

    /**
     * @var array
     */
    public array $lineItems;

    /**
     * @var float
     */
    public float $totalWeight;

    /**
     * @var MockUser
     */
    public MockUser $user;

    /**
     * @var MockAddress
     */
    public MockAddress $billingAddress;

    /**
     * @var MockAddress
     */
    public MockAddress $shippingAddress;

    /**
     * @var MockAddress
     */
    public MockAddress $estimatedBillingAddress;

    /**
     * @var MockAddress
     */
    public MockAddress $estimatedShippingAddress;

    public function __construct()
    {
    }

    /**
     * @return array
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @return bool
     */
    public function hasLineItems(): bool
    {
        return count($this->lineItems) > 0;
    }

    /**
     * @return float
     */
    public function getTotalWeight(): float
    {
        return $this->totalWeight;
    }

    /**
     * @param $key
     * @param $value
     * @return void
     */
    public function clearNotices($key, $value): void
    {
    }

    /**
     * @param $notice
     * @return void
     */
    public function addNotice($notice): void
    {
    }
}

class MockCategoryGroup
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $title;

    /**
     * @var string
     */
    public string $slug;

    public function __construct()
    {
    }
}

class MockCategory
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $title;

    /**
     * @var string
     */
    public string $slug;

    /**
     * @var int
     */
    public int $groupId;

    /**
     * @var MockCategoryGroup
     */
    public MockCategoryGroup $group;

    public function __construct()
    {
    }
}

class MockProductType
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $handle;

    public function __construct()
    {
    }
}

class MockProduct
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int
     */
    public int $typeId;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var array
     */
    public array $variants;

    /**
     * @var string
     */
    public string $defaultSku;

    /**
     * @var MockProductType
     */
    public MockProductType $type;

    /**
     * @var int
     */
    public int $shippingCategoryId;

    /**
     * @var array
     */
    public array $colorProductLinesCategory;

    public function __construct()
    {
    }
}

class MockVariant
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int
     */
    public int $minQty;

    /**
     * @var int
     */
    public int $maxQty;

    /**
     * @var int
     */
    public int $stock;

    /**
     * @var float
     */
    public float $price;

    /**
     * @var float
     */
    public float $width;

    /**
     * @var float
     */
    public float $length;

    /**
     * @var float
     */
    public float $height;

    /**
     * @var float
     */
    public float $weight;

    /**
     * @var bool
     */
    public bool $isDefault;

    /**
     * @var MockProduct
     */
    public MockProduct $product;

    /**
     * @var bool
     */
    public bool $hasUnlimitedStock;

    public function __construct()
    {
    }
}

class MockLineItem
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int
     */
    public int $qty;

    /**
     * @var string
     */
    public string $sku;

    /**
     * @var int
     */
    public int $orderId;

    /**
     * @var float
     */
    public float $price;

    /**
     * @var float
     */
    public float $width;

    /**
     * @var float
     */
    public float $length;

    /**
     * @var float
     */
    public float $height;

    /**
     * @var float
     */
    public float $weight;

    /**
     * @var array
     */
    public array $options;

    /**
     * @var float
     */
    public float $salePrice;

    /**
     * @var MockVariant
     */
    public MockVariant $purchasable;

    /**
     * @var string
     */
    public string $description;

    public function __construct()
    {
    }
}

class MockAddress
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int
     */
    public int $stateId;

    /**
     * @var string
     */
    public string $city;

    /**
     * @var string
     */
    public string $phone;

    /**
     * @var string
     */
    public string $title;

    /**
     * @var string
     */
    public string $email;

    /**
     * @var int
     */
    public int $countryId;

    /**
     * @var string
     */
    public string $postalCode;

    /**
     * @var string
     */
    public string $lastName;

    /**
     * @var string
     */
    public string $fullName;

    /**
     * @var string
     */
    public string $address1;

    /**
     * @var string
     */
    public string $address2;

    /**
     * @var string
     */
    public string $address3;

    /**
     * @var string
     */
    public string $firstName;

    /**
     * @var string
     */
    public string $stateName;

    /**
     * @var string
     */
    public string $countryIso;

    /**
     * @var string
     */
    public string $businessName;

    /**
     * @var MockAddressState
     */
    public MockAddressState $state;

    public function __construct()
    {
    }
}

class MockAddressState
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $abbreviation;

    public function __construct()
    {
    }
}

class MockUser
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $email;

    /**
     * @var array
     */
    public array $groups;

    /**
     * @var string
     */
    public string $fullName;

    public function __construct()
    {
    }

    /**
     * @param $group
     * @return bool
     */
    public function isInGroup($group): bool
    {
        if (Craft::$app->getEdition() !== Craft::Pro) {
            return false;
        }

        if (is_object($group) && $group instanceof MockUserGroup) {
            $group = $group->id;
        }

        if (is_numeric($group)) {
            return in_array(
                $group,
                ArrayHelper::getColumn($this->getGroups(), 'id'),
                false,
            );
        }

        return in_array(
            $group,
            ArrayHelper::getColumn($this->getGroups(), 'handle'),
            true,
        );
    }

    /**
     * @param $groups
     * @return void
     */
    public function setGroups($groups): void
    {
        $this->groups = $groups;
    }

    /**
     * @return array
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}

class MockUserGroup
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $handle;

    public function __construct()
    {
    }
}
