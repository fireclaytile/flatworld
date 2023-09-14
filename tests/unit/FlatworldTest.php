<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\tests\unit;

use Craft;
use Exception;
use UnitTester;
use craft\helpers\Json;
use verbb\postie\Postie;
use Codeception\Test\Unit;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use craft\helpers\ArrayHelper;
use yii\base\InvalidConfigException;
use fireclaytile\flatworld\providers\Flatworld;
use fireclaytile\flatworld\variables\FlatworldVariable;

class FlatworldProviderTest extends Unit {
	/**
	 * @var MockOrder $order
	 */
	protected MockOrder $order;

	/**
	 * @var Flatworld
	 */
	protected Flatworld $flatworld;

	/**
	 * @var UnitTester
	 */
	protected UnitTester $tester;

	/**
	 * @var array
	 */
	public array $mockApiParcelResponse;

	/**
	 * @var array
	 */
	public array $mockApiResponse;

	/**
	 * @var array
	 */
	public array $mockServiceList;

	/**
	 * @var FlatworldVariable
	 */
	protected FlatworldVariable $flatworldVariable;

	/**
	 * @return void
	 */
	protected function _before(): void {
		parent::_before();

		Craft::$app->setEdition(Craft::Pro);

		$this->flatworldVariable = new FlatworldVariable();

		$this->flatworld = Postie::getInstance()->getProviders()->getProviderByHandle('flatworld');

		$this->prepareMockData();
	}

	/**
	 * @return void
	 */
	public function testConfigOptionsSpecificToFlatworldAreValidAndCorrect(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
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

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testSettingsSpecificToFlatworldAreValidAndCorrect(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$this->assertNotEmpty($this->flatworld->getSetting('username'));
		$this->assertSame('Fireclay', $this->flatworld->getSetting('username'));

		// $this->assertNotEmpty($this->flatworld->getSetting('apiUrl'));
		// $this->assertSame('', $this->flatworld->getSetting('apiUrl'));

		// $this->assertNotEmpty($this->flatworld->getSetting('licenseId'));
		// $this->assertSame('', $this->flatworld->getSetting('licenseId'));

		// $this->assertNotEmpty($this->flatworld->getSetting('licenseKey'));
		// $this->assertSame('', $this->flatworld->getSetting('licenseKey'));

		// $this->assertNotEmpty($this->flatworld->getSetting('upsLicenseId'));
		// $this->assertSame('', $this->flatworld->getSetting('upsLicenseId'));

		$this->assertNotEmpty($this->flatworld->getSetting('totalMaxWeight'));
		$this->assertSame('39750', $this->flatworld->getSetting('totalMaxWeight'));

		$this->assertNotEmpty($this->flatworld->getSetting('weightThreshold'));
		$this->assertSame('150', $this->flatworld->getSetting('weightThreshold'));

		$this->assertNotEmpty($this->flatworld->getSetting('weightLimitMessage'));
		$this->assertSame('Shipping weight limit reached. Please contact Fireclay Tile Salesperson.', $this->flatworld->getSetting('weightLimitMessage'));

		$weightPerSquareFoot = $this->flatworld->getSetting('weightPerSquareFoot');

		$this->assertNotEmpty($weightPerSquareFoot);
		$this->assertIsArray($weightPerSquareFoot);

		$this->assertNotEmpty($weightPerSquareFoot[0][0]);
		$this->assertSame('tile', $weightPerSquareFoot[0][0]);

		$this->assertNotEmpty($weightPerSquareFoot[0][2]);
		$this->assertSame('4.5', $weightPerSquareFoot[0][2]);

		$this->assertNotEmpty($this->flatworld->getSetting('displayDebugMessages'));
		$this->assertTrue((bool) $this->flatworld->getSetting('displayDebugMessages'));

		$this->assertNotEmpty($this->flatworld->getSetting('flatRateCarrierCost'));
		$this->assertSame('8.0', $this->flatworld->getSetting('flatRateCarrierCost'));

		$carrierClassOfServices = $this->flatworld->getSetting('carrierClassOfServices');

		$this->assertIsArray($carrierClassOfServices);
		$this->assertNotEmpty($carrierClassOfServices);
		$this->assertSame($this->mockServiceList, $carrierClassOfServices);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
	public function testSettingsTemplateHasFlatworldSpecificFields(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$settingsHtml = $this->flatworld->getSettingsHtml();

		$this->assertNotEmpty($settingsHtml);
		$this->assertStringContainsString('apiUrl-label', $settingsHtml);
		$this->assertStringContainsString('username-label', $settingsHtml);
		$this->assertStringContainsString('licenseId-label', $settingsHtml);
		$this->assertStringContainsString('licenseKey-label', $settingsHtml);
		$this->assertStringContainsString('upsLicenseId-label', $settingsHtml);
		$this->assertStringContainsString('totalMaxWeight-label', $settingsHtml);
		$this->assertStringContainsString('weightThreshold-label', $settingsHtml);
		$this->assertStringContainsString('weightLimitMessage-label', $settingsHtml);
		$this->assertStringContainsString('weightPerSquareFoot-heading-1', $settingsHtml);
		$this->assertStringContainsString('weightPerSquareFoot-heading-2', $settingsHtml);
		$this->assertStringContainsString('weightPerSquareFoot-heading-3', $settingsHtml);
		$this->assertStringContainsString('displayDebugMessages-label', $settingsHtml);
		$this->assertStringContainsString('flatRateCarrierCost-label', $settingsHtml);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testDisplayNameReturnsAValidString(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$displayName = $this->flatworld->displayName();

		$this->assertNotEmpty($displayName);
		$this->assertSame('Flatworld', $displayName);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testIconUrlReturnsAValidString(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$iconUrl = $this->flatworld->getIconUrl();

		$this->assertNotEmpty($iconUrl);
		$this->assertSame('https://flatworld.test:80/cpresources/17b8c0e2/Flatworld.svg?v=1694438761', $iconUrl);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testServiceListReturnsAValidArrayOfServices(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$serviceList = $this->flatworld->getServiceList();

		$this->assertIsArray($serviceList);
		$this->assertNotEmpty($serviceList);
		$this->assertSame($this->mockServiceList, $serviceList);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	public function testFindQuickestRate() {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

        // $now = \DateTime::createFromFormat('Y/m/d', '2024/09/06');

        $quickestRate = $this->flatworld->findQuickestRate($this->mockApiParcelResponse);

		$expectedRate = Json::decode('{
			"CarrierId": "4028|03",
			"CarrierMaximumCoverage": "100",
			"CarrierName": "UPS",
			"Days": "UPS Ground",
			"Direct": "D",
			"EstimatedDeliveryDate": "2024/09/08",
			"PublishedRate": "44.22",
			"SCAC": "UPGD",
			"ServiceLevel": "UPS Ground",
			"SubTotal": "23.34",
			"Total": "28.01",
			"TransitDays": "1",
			"Type": "parcel"
		}');

        // Assert that $quickestRate is the expected rate with the quickest delivery
        $this->assertEquals($expectedRate, $quickestRate);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
    }

	/**
	 * @return void
	 */
	// public function testFetchConnectionReturnsTrue(): void {
	// 	$connection = $this->flatworld->fetchConnection();

	// 	$this->assertIsBool($connection);
	// 	$this->assertTrue($connection);
	// }

	/**
	 * @return void
	 */
	public function testResponseRates(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		
		$rates = $this->flatworld->responseRates();

		$this->assertIsArray($rates);
		$this->assertNotEmpty($rates);

		$keys = array_keys($rates);

		$this->assertIsArray($keys);
		$this->assertNotEmpty($keys);


		$this->assertArrayHasKey('arrival', $rates[$keys[0]]);
		$this->assertArrayHasKey('transitTime', $rates[$keys[0]]);
		$this->assertArrayHasKey('arrivalDateText', $rates[$keys[0]]);
		$this->assertArrayHasKey('amount', $rates[$keys[0]]);

		// $this->assertSame('1-3 days', $rates[$keys[1]]['arrival']);
		// $this->assertSame(1, $rates[$keys[1]]['transitTime']);
		// $this->assertSame('WED - 1/26/2022', $rates[$keys[1]]['arrivalDateText']);
		// $this->assertSame(27.12, $rates[$keys[1]]['amount']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testTransitTimesShowCorrectArrivalDays(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->assertEmpty($this->flatworld->getArrival(0));
		$this->assertSame('', $this->flatworld->getArrival(0));

		$this->assertNotEmpty($this->flatworld->getArrival(1));
		$this->assertSame('1-3 days', $this->flatworld->getArrival(1));

		$this->assertNotEmpty($this->flatworld->getArrival(2));
		$this->assertSame('1-3 days', $this->flatworld->getArrival(2));

		$this->assertNotEmpty($this->flatworld->getArrival(3));
		$this->assertSame('3-7 days', $this->flatworld->getArrival(3));

		$this->assertNotEmpty($this->flatworld->getArrival(4));
		$this->assertSame('3-7 days', $this->flatworld->getArrival(4));

		$this->assertNotEmpty($this->flatworld->getArrival(5));
		$this->assertSame('3-7 days', $this->flatworld->getArrival(5));

		$this->assertNotEmpty($this->flatworld->getArrival(6));
		$this->assertSame('3-7 days', $this->flatworld->getArrival(6));

		$this->assertNotEmpty($this->flatworld->getArrival(7));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(7));

		$this->assertNotEmpty($this->flatworld->getArrival(8));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(8));

		$this->assertNotEmpty($this->flatworld->getArrival(9));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(9));

		$this->assertNotEmpty($this->flatworld->getArrival(10));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(10));

		$this->assertNotEmpty($this->flatworld->getArrival(11));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(11));

		$this->assertNotEmpty($this->flatworld->getArrival(12));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(12));

		$this->assertNotEmpty($this->flatworld->getArrival(13));
		$this->assertSame('7-14 days', $this->flatworld->getArrival(13));

		$this->assertNotEmpty($this->flatworld->getArrival(14));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(14));

		$this->assertNotEmpty($this->flatworld->getArrival(15));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(15));

		$this->assertNotEmpty($this->flatworld->getArrival(16));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(16));

		$this->assertNotEmpty($this->flatworld->getArrival(17));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(17));

		$this->assertNotEmpty($this->flatworld->getArrival(18));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(18));

		$this->assertNotEmpty($this->flatworld->getArrival(19));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(19));

		$this->assertNotEmpty($this->flatworld->getArrival(20));
		$this->assertSame('14-21 days', $this->flatworld->getArrival(20));

		$this->assertNotEmpty($this->flatworld->getArrival(21));
		$this->assertSame('21 days or more', $this->flatworld->getArrival(21));

		$this->assertNotEmpty($this->flatworld->getArrival(22));
		$this->assertSame('21 days or more', $this->flatworld->getArrival(22));

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testDisplayDebugMessageDoesNotWriteToLogFileWhenDisabled(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		// Explicitly setting display debug messages to false
		$this->flatworld->settings['displayDebugMessages'] = false;

		$this->assertIsBool($this->flatworld->displayDebugMessage('This was not written to a log file!'));
		$this->assertFalse($this->flatworld->displayDebugMessage('This was not written to a log file!'));

		$this->flatworld->settings['displayDebugMessages'] = true;
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testDisplayDebugMessageWritesToLogFileWhenEnabled(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		// Explicitly setting display debug messages to true
		$this->flatworld->settings['displayDebugMessages'] = true;

		$this->assertIsBool($this->flatworld->displayDebugMessage('This was written to a log file!'));
		$this->assertTrue($this->flatworld->displayDebugMessage('This was written to a log file!'));

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testSetPackageDetailsListReturnsValidArray(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertNotEmpty($packageDetailsList);

		$this->assertArrayHasKey(0, $packageDetailsList);

		$this->assertIsArray($packageDetailsList[0]);
		$this->assertNotEmpty($packageDetailsList[0]);

		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);

		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testGetPayloadReturnsValidArray(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();

		$payload = $this->flatworld->getPayload();

		$this->assertIsArray($payload);
		$this->assertNotEmpty($payload);

		$this->assertArrayHasKey('PackageDetailsList', $payload);
		$this->assertArrayHasKey('Location', $payload);
		$this->assertArrayHasKey('LicenseID', $payload);
		$this->assertArrayHasKey('UpsLicenseID', $payload);

		$packageDetailsList = $payload['PackageDetailsList'];

		$this->assertIsArray($packageDetailsList);
		$this->assertNotEmpty($packageDetailsList);

		$this->assertArrayHasKey(0, $packageDetailsList);

		$this->assertIsArray($packageDetailsList[0]);
		$this->assertNotEmpty($packageDetailsList[0]);

		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);

		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->assertSame('Fireclay', $payload['Location']);
		// $this->assertSame('9737b779-a071-980d-754a-d91d4a58bb63', $payload['LicenseID']);
		// $this->assertSame('6a59faa9-187b-2f78-7f35-046358b4be26', $payload['UpsLicenseID']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testFilteringOutAddonsFromTwoLineItemsLeavesOneLineItem(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);

		$this->assertSame(6, count($mockOrder->getLineItems()));

		$this->flatworld->filterOutAddons();

		$mockOrderClone = $this->flatworld->getOrder();

		$this->assertSame(5, count($mockOrderClone->getLineItems()));

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testOrderContainsStandardProducts(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);

		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->filterOutSampleProducts();

		$this->flatworld->countProductTypes();

		$this->assertTrue($this->flatworld->orderContainsStandardProducts());
		$this->assertFalse($this->flatworld->orderContainsSampleProducts());
		$this->assertFalse($this->flatworld->orderContainsMerchandise());

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testOrderContainsSampleProducts(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);

		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->filterOutStandardProducts();

		$this->flatworld->countProductTypes();

		$this->assertTrue($this->flatworld->orderContainsSampleProducts());
		$this->assertFalse($this->flatworld->orderContainsStandardProducts());
		$this->assertFalse($this->flatworld->orderContainsMerchandise());

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testOrderContainsMerchandise(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);

		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutSampleProducts();
		$this->flatworld->filterOutStandardProducts();

		$this->flatworld->countProductTypes();

		$this->assertTrue($this->flatworld->orderContainsMerchandise());
		$this->assertFalse($this->flatworld->orderContainsSampleProducts());
		$this->assertFalse($this->flatworld->orderContainsStandardProducts());

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testCalculatePiecesForTileBrickAndGlass(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutSampleProducts();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();

		$lineItems = $this->flatworld->getOrder()->getLineItems();

		// Weights: Tile is 4.5lbs, Brick is 5lbs, Glass is 3lbs.
		// Formula is (weight per sq ft / variant weight) * sq ft

		// We know line item #2 is a tile...
		// Explicitly setting qty to 25
		// so... (4.5 / 0.712) * 25 = 159
		$pieces = $this->flatworld->calculatePieces(4.5, $lineItems[2]->weight, 25);

		$this->assertSame(159, intval($pieces));

		// We know line item #4 is a brick...
		// Explicitly setting qty to 37
		// so... (5 / 0.712) * 37 = 260
		$pieces = $this->flatworld->calculatePieces(5, $lineItems[3]->weight, 37);

		$this->assertSame(260, intval($pieces));

		// We know line item #5 is a glass...
		// Explicitly setting qty to 18
		// so... (3 / 0.712) * 18 = 76
		$pieces = $this->flatworld->calculatePieces(3, $lineItems[4]->weight, 18);

		$this->assertSame(76, intval($pieces));

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testSetTotalWeightReturnsFormattedValue(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setTotalWeight(14000);

		$this->assertSame(14000.00, $this->flatworld->getTotalWeight());

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->filterOutAddons();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();

		$this->assertSame(19.94, $this->flatworld->getTotalWeight());

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function testCheckTotalWeightReturnsFalseWhenAnOrderWeightIsLessThanOrEqualToZero(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->filterOutAddons();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();

		// Explicitly setting line items to an empty array so total weight will be zero
		$order = $this->flatworld->getOrder();
		$order->lineItems = [];

		$this->flatworld->setTotalWeight();

		$result = $this->flatworld->checkTotalWeight();

		$this->assertIsBool($result);
		$this->assertFalse($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function testCheckTotalWeightReturnsTrueWhenAnOrderWeightIsGreaterThanZero(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->filterOutAddons();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();

		$result = $this->flatworld->checkTotalWeight();

		$this->assertIsBool($result);
		$this->assertTrue($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testWeightLimitations(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		// Explicitly setting total weights
		$this->flatworld->setTotalWeight(120);
		$weightLimitReached1 = $this->flatworld->weightLimitReached();

		$this->flatworld->setTotalWeight(39750);
		$weightLimitReached2 = $this->flatworld->weightLimitReached();

		$this->flatworld->setTotalWeight(39751);
		$weightLimitReached3 = $this->flatworld->weightLimitReached();

		$this->flatworld->setTotalWeight(41100);
		$weightLimitReached4 = $this->flatworld->weightLimitReached();

		$this->assertIsBool($weightLimitReached1);
		$this->assertFalse($weightLimitReached1);

		$this->assertIsBool($weightLimitReached2);
		$this->assertFalse($weightLimitReached2);

		$this->assertIsBool($weightLimitReached3);
		$this->assertTrue($weightLimitReached3);

		$this->assertIsBool($weightLimitReached4);
		$this->assertTrue($weightLimitReached4);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testCheckWeightLimitReturnsFalseWhenWeightLimitIsReached(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->setTotalWeight(39751);

		$result = $this->flatworld->checkWeightLimit();

		$this->assertIsBool($result);
		$this->assertFalse($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testCheckWeightLimitReturnsTrueWhenWeightLimitIsNotReached(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->setTotalWeight(751);

		$result = $this->flatworld->checkWeightLimit();

		$this->assertIsBool($result);
		$this->assertTrue($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testWeightThresholds(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		// Explicitly setting total weights
		$this->flatworld->setTotalWeight(120);
		$weightThresholdReached1 = $this->flatworld->underWeightThreshold();
		$this->assertIsBool($weightThresholdReached1);
		$this->assertTrue($weightThresholdReached1);

		$this->flatworld->setTotalWeight(150);
		$weightThresholdReached2 = $this->flatworld->underWeightThreshold();
		$this->assertIsBool($weightThresholdReached2);
		$this->assertTrue($weightThresholdReached2);

		$this->flatworld->setTotalWeight(151);
		$weightThresholdReached3 = $this->flatworld->underWeightThreshold();
		$this->assertIsBool($weightThresholdReached3);
		$this->assertFalse($weightThresholdReached3);

		$this->flatworld->setTotalWeight(400);
		$weightThresholdReached4 = $this->flatworld->underWeightThreshold();
		$this->assertIsBool($weightThresholdReached4);
		$this->assertFalse($weightThresholdReached4);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsStandardProductsAndIsUnderWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(false);
		$this->flatworld->setOrderContainsSampleProducts(false);
		$this->flatworld->setTotalWeight(140);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsPalletWhenOrderContainsStandardProductsAndOverWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(false);
		$this->flatworld->setOrderContainsSampleProducts(false);
		$this->flatworld->setTotalWeight(1500);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('PackageNumber', $packageDetailsList[0]);
		$this->assertSame('Pallet', $packageDetailsList[0]['PackageNumber']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsSampleProductsOnly(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(false);
		$this->flatworld->setOrderContainsMerchandise(false);
		$this->flatworld->setOrderContainsSampleProducts(true);
		$this->flatworld->setTotalWeight(150);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsMerchandiseOnly(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(false);
		$this->flatworld->setOrderContainsMerchandise(true);
		$this->flatworld->setOrderContainsSampleProducts(false);
		$this->flatworld->setTotalWeight(150);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsSampleProductsAndMerchandiseOnly(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(false);
		$this->flatworld->setOrderContainsMerchandise(true);
		$this->flatworld->setOrderContainsSampleProducts(true);
		$this->flatworld->setTotalWeight(150);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsPalletWhenOrderContainsStandardProductsAndSamplesAndOverWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(false);
		$this->flatworld->setOrderContainsSampleProducts(true);
		$this->flatworld->setTotalWeight(14900);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('PackageNumber', $packageDetailsList[0]);
		$this->assertSame('Pallet', $packageDetailsList[0]['PackageNumber']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsStandardProductsAndSamplesAndUnderWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(false);
		$this->flatworld->setOrderContainsSampleProducts(true);
		$this->flatworld->setTotalWeight(149);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsPalletWhenOrderContainsStandardProductsAndMerchandiseAndOverWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(true);
		$this->flatworld->setOrderContainsSampleProducts(false);
		$this->flatworld->setTotalWeight(11400);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('PackageNumber', $packageDetailsList[0]);
		$this->assertSame('Pallet', $packageDetailsList[0]['PackageNumber']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsStandardProductsAndMerchandiseAndUnderWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(true);
		$this->flatworld->setOrderContainsSampleProducts(false);
		$this->flatworld->setTotalWeight(100);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsPalletWhenOrderContainsStandardProductsAndSampleProductsAndMerchandiseAndOverWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(true);
		$this->flatworld->setOrderContainsSampleProducts(true);
		$this->flatworld->setTotalWeight(10000);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('PackageNumber', $packageDetailsList[0]);
		$this->assertSame('Pallet', $packageDetailsList[0]['PackageNumber']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testGetPackageDetailsListReturnsSingleBoxWhenOrderContainsStandardProductsAndSampleProductsAndMerchandiseAndUnderWeightThreshold(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setOrderContainsMerchandise(true);
		$this->flatworld->setOrderContainsSampleProducts(true);
		$this->flatworld->setTotalWeight(100);
		$this->flatworld->setPackageDetailsList();

		$packageDetailsList = $this->flatworld->getPackageDetailsList();

		$this->assertIsArray($packageDetailsList);
		$this->assertArrayHasKey(0, $packageDetailsList);
		$this->assertArrayHasKey('Weight', $packageDetailsList[0]);
		$this->assertArrayHasKey('Name', $packageDetailsList[0]);
		$this->assertSame('SingleBox', $packageDetailsList[0]['Name']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testRatesReturnsAnEmptyArrayWhenAnOrderIsNotFound(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$rates = $this->flatworldVariable->getRates(0);

		$this->assertEmpty($rates);
		$this->assertIsArray($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testRatesReturnsAnEmptyArrayWhenAnOrderHasNoLineItems(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(false, false);

		$rates = $this->flatworldVariable->getRates($mockOrder->id);

		$this->assertEmpty($rates);
		$this->assertIsArray($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testRatesCacheIsUsedWhenMultipleRequestsAreMade(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$file = Craft::getAlias('@storage/logs/flatworld.log');

		unlink($file);

		Craft::$app->cache->flush();

		$mockOrder = $this->createMockOrder();

		$this->createNewApiRequest($mockOrder);
		$this->createNewApiRequest($mockOrder);
		$this->createNewApiRequest($mockOrder);
		$rates = $this->createNewApiRequest($mockOrder);

		$this->assertIsArray($rates);
		$this->assertNotEmpty($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function testFetchShippingRatesReturnsEmptyRatesWhenAnOrderHasNoLineItems(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(false, false);

		$rates = $this->flatworld->fetchShippingRates($mockOrder);

		$this->assertIsArray($rates);
		$this->assertEmpty($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testCheckLineItemsReturnsFalseWhenAnOrderHasNoLineItems(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(false, false);

		$this->flatworld->setOrder($mockOrder);

		$result = $this->flatworld->checkLineItems();

		$this->assertIsBool($result);
		$this->assertFalse($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testCheckLineItemsReturnsTrueWhenAnOrderHasLineItems(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(true, false);

		$this->flatworld->setOrder($mockOrder);

		$result = $this->flatworld->checkLineItems();

		$this->assertIsBool($result);
		$this->assertTrue($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testCheckLineItemRequiredFieldsReturnsFalseWhenAnOrderHasInValidLineItems(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(true, false);

		$this->flatworld->setOrder($mockOrder);

		$lineItems = $this->flatworld->getOrder()->getLineItems();

		// Explicitly setting qty and weight to null for line items 2 & 3
		$lineItems[1]->qty = 0;
		$lineItems[2]->weight = 0.0;

		$result = $this->flatworld->checkLineItemRequiredFields();

		$this->assertIsBool($result);
		$this->assertFalse($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testCheckLineItemRequiredFieldsReturnsTrueWhenAnOrderHasValidLineItems(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(true, false);

		$this->flatworld->setOrder($mockOrder);

		$result = $this->flatworld->checkLineItemRequiredFields();

		$this->assertIsBool($result);
		$this->assertTrue($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function testFetchShippingRatesReturnsEmptyRatesWhenAnOrderHasNoShippingAddress(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(true, false);

		$rates = $this->flatworld->fetchShippingRates($mockOrder);

		$this->assertIsArray($rates);
		$this->assertEmpty($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * Test fetchShippingRates method with a valid order. Currently uses fake rates data.
	 * @return void
	 * @throws Exception
	 */
	public function testFetchShippingRatesReturnsParcelResults(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$rates = $this->flatworld->fetchShippingRates($mockOrder);
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: rates: '.print_r($rates, true));

		$this->assertIsArray($rates);
		$this->assertNotEmpty($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function testCheckShippingAddressesReturnsFalseWhenAnOrderHasNoShippingAddress(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder(true, false);

		$this->flatworld->setOrder($mockOrder);

		$result = $this->flatworld->checkShippingAddress();

		$this->assertIsBool($result);
		$this->assertFalse($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function testCheckShippingAddressesReturnsTrueWhenAnOrderHasNoShippingAddress(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);

		$result = $this->flatworld->checkShippingAddress();

		$this->assertIsBool($result);
		$this->assertTrue($result);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsSampleProductsDoesReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutStandardProducts();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();
		$flatRateAmount = $this->flatworld->getFlatRateAmount();

		$this->assertIsArray($rates);
		$this->assertArrayHasKey($flatRateHandle, $rates);
		$this->assertArrayHasKey('amount', $rates[$flatRateHandle]);
		$this->assertSame($flatRateAmount, $rates[$flatRateHandle]['amount']);

		// Asserts that the flat rate carrier is the first element
		$firstCarrier = array_slice($rates, 0, 1);
		$firstCarrierHandle = key($firstCarrier);

		$this->assertSame($flatRateHandle, $firstCarrierHandle);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsStandardProductsDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsMerchandiseProductsDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutStandardProducts();
		$this->flatworld->filterOutSampleProducts();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsMerchandiseAndStandardProductsDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutSampleProducts();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsStandardProductsAndSampleProductsDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsMerchandiseAndSampleProductsDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutStandardProducts();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsMerchandiseStandardProductsAndSamplesDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$mockOrder = $this->createMockOrder();

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 */
	public function testNoCarriersFoundDoesNotReturnFlatRateAsCheapestCarrier(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrderContainsStandardProducts(true);
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		// This causes our carrier matching logic to not match and therefore test our flat rate logic
		$this->flatworld->settings['carrierClassOfServices'] = [];

		$rates = $this->flatworld->responseRates();

		$flatRateHandle = $this->flatworld->getFlatRateHandle();

		$this->assertIsArray($rates);
		$this->assertArrayNotHasKey($flatRateHandle, $rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsSampleProductsOnlyReturnsFirstCarrierWithZeroCostForCustomersTrade15Group(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$mockOrder = $this->createMockOrder();

		$userGroup15 = new MockUserGroup();
		$userGroup15->id = 15;
		$userGroup15->name = 'Customers Trade 15';
		$userGroup15->handle = 'customersTrade15';

		$mockOrder->user->setGroups([
			$userGroup15,
		]);

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->filterOutStandardProducts();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		// $firstCarrier = array_slice($rates, 0, 1);
		// $firstCarrierHandle = key($firstCarrier);

		$this->assertIsArray($rates);
		$this->assertArrayHasKey('amount', $rates);
		$this->assertEquals(0, $rates['amount']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsMixedProductsDoesNotReturnsFirstCarrierWithZeroCostForCustomersTrade15Group(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$mockOrder = $this->createMockOrder();

		$userGroup15 = new MockUserGroup();
		$userGroup15->id = 15;
		$userGroup15->name = 'Customers Trade 15';
		$userGroup15->handle = 'customersTrade15';

		$mockOrder->user->setGroups([
			$userGroup15,
		]);

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		// $firstCarrier = array_slice($rates, 0, 1);
		// $firstCarrierHandle = key($firstCarrier);

		$this->assertIsArray($rates);
		$this->assertArrayHasKey('amount', $rates);
		$this->assertNotEquals(0, $rates['amount']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	public function testOrderContainsSampleProductsOnlyDoesNotReturnCarrierWithZeroAmountForNonCustomersTrade15Group(): void {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');
		
		$mockOrder = $this->createMockOrder();

		$userGroup3 = new MockUserGroup();
		$userGroup3->id = 3;
		$userGroup3->name = 'Mock Group 3';
		$userGroup3->handle = 'mockGroup3';

		$mockOrder->user->setGroups([
			$userGroup3,
		]);

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->filterOutStandardProducts();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();
		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		// $firstCarrier = array_slice($rates, 0, 1);
		// $firstCarrierHandle = key($firstCarrier);

		$this->assertIsArray($rates);
		$this->assertArrayHasKey('amount', $rates);
		$this->assertNotEquals(0, $rates['amount']);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
	}

	/**
	 * @param $mockOrder
	 * @return array
	 * @throws InvalidConfigException
	 */
	protected function createNewApiRequest($mockOrder): array {
		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: running...');

		$this->flatworld->setOrder($mockOrder);
		$this->flatworld->checkLineItems();
		$this->flatworld->checkLineItemRequiredFields();
		$this->flatworld->checkShippingAddress();
		$this->flatworld->filterOutAddons();
		$this->flatworld->filterOutStandardProducts();
		$this->flatworld->filterOutMerchandise();
		$this->flatworld->countProductTypes();
		$this->flatworld->setPieces();
		$this->flatworld->setTotalWeight();
		$this->flatworld->checkWeightLimit();
		$this->flatworld->setPackageDetailsList();

		// Lets check the cache for rates - this will be an array or be false
		$ratesCache = $this->flatworld->getRatesCache();

		if (! empty($ratesCache) && is_array($ratesCache)) {
			return $ratesCache;
		}

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: getRates :: Making new API request');

		$this->flatworld->setResponse($this->mockApiParcelResponse);

		$rates = $this->flatworld->responseRates();

		$this->flatworld->setRatesCache($rates);

		$this->flatworld->displayDebugMessage('FlatworldTest.php :: '.__FUNCTION__.' :: done...');
		
		return $rates;
	}

	/**
	 * @return void
	 */
	protected function prepareMockData(): void {
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
			'apiUrl' => '',
			'username' => 'Fireclay',
			'licenseId' => '',
			'licenseKey' => '',
			'upsLicenseId' => '',
			'totalMaxWeight' => '39750',
			'weightThreshold' => '150',
			'weightLimitMessage' => 'Shipping weight limit reached. Please contact Fireclay Tile Salesperson.',
			'weightPerSquareFoot' => [
				[
					0 => 'tile',
					1 => '',
					2 => '4.5'
				], [
					0 => 'quickShipTile',
					1 => '',
					2 => '4.5'
				], [
					0 => 'brick',
					1 => '',
					2 => '5'
				], [
					0 => 'quickShipBrick',
					1 => '',
					2 => '5'
				], [
					0 => 'glass',
					1 => '',
					2 => '3'
				], [
					0 => 'quickShipGlass',
					1 => '',
					2 => '3'
				], [
					0 => 'quickShipEssentials',
					1 => '',
					2 => '3.4'
				], [
					0 => 'quickShipSeconds',
					1 => 'tile',
					2 => '3.4'
				], [
					0 => 'quickShipSeconds',
					1 => 'brick',
					2 => '5'
				], [
					0 => 'quickShipSeconds',
					1 => 'glass',
					2 => '3'
				]
			],
			'displayDebugMessages' => '1',
			'flatRateCarrierName' => 'USPS Flat Rate',
			'flatRateCarrierCost' => '8.0',
			'carrierClassOfServices' => $this->mockServiceList
		];

		$this->mockApiParcelResponse = Json::decode('
			[
				{
					"CarrierId": "4028|03",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS Ground",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/08",
					"PublishedRate": "44.22",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS Ground",
					"SubTotal": "23.34",
					"Total": "28.01",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "4028|12",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS 3 Day Select",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/12",
					"PublishedRate": "106.22",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS 3 Day Select",
					"SubTotal": "42.48",
					"Total": "50.98",
					"TransitDays": "3",
					"Type": "parcel"
				},
				{
					"CarrierId": "4028|02",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS 2nd Day Air",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/09",
					"PublishedRate": "147.94",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS 2nd Day Air",
					"SubTotal": "42.9",
					"Total": "51.48",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "4028|13",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS Next Day Air Saver",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/08",
					"EstimatedDeliveryTime": "23:00 PT",
					"PublishedRate": "206.6",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS Next Day Air Saver",
					"SubTotal": "52.46",
					"Total": "62.95",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "4028|01",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS Next Day Air",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/08",
					"EstimatedDeliveryTime": "10:30 PT",
					"PublishedRate": "223.04",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS Next Day Air",
					"SubTotal": "55.34",
					"Total": "66.41",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "4028|59",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS Second Day Air AM",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/08",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "166.5",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS Second Day Air AM",
					"SubTotal": "58.28",
					"Total": "69.94",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "5624|First",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "United States Postal Service",
					"Days": "USPS First Class",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/09",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "64.3",
					"SCAC": "USPS",
					"ServiceLevel": "USPS First Class",
					"SubTotal": "62.997",
					"Total": "75.597",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "5624|GroundAdvantage",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "United States Postal Service",
					"Days": "USPS GroundAdvantage",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/09",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "64.3",
					"SCAC": "USPS",
					"ServiceLevel": "USPS GroundAdvantage",
					"SubTotal": "62.997",
					"Total": "75.597",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "5624|ParcelSelect",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "United States Postal Service",
					"Days": "USPS Parcel Select",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/09",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "64.3",
					"SCAC": "USPS",
					"ServiceLevel": "USPS Parcel Select",
					"SubTotal": "62.997",
					"Total": "75.597",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "5624|Priority",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "United States Postal Service",
					"Days": "USPS Priority",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/09",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "67.7",
					"SCAC": "USPS",
					"ServiceLevel": "USPS Priority",
					"SubTotal": "66.723",
					"Total": "80.063",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "5624|Express",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "United States Postal Service",
					"Days": "USPS Express",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/09",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "217.5",
					"SCAC": "USPS",
					"ServiceLevel": "USPS Express",
					"SubTotal": "220.225",
					"Total": "264.275",
					"TransitDays": "1",
					"Type": "parcel"
				},
				{
					"CarrierId": "4028|14",
					"CarrierMaximumCoverage": "100",
					"CarrierName": "UPS",
					"Days": "UPS Next Day Air Early AM",
					"Direct": "D",
					"EstimatedDeliveryDate": "2024/09/08",
					"EstimatedDeliveryTime": "08:00 PT",
					"PublishedRate": "294",
					"SCAC": "UPGD",
					"ServiceLevel": "UPS Next Day Air Early AM",
					"SubTotal": "294",
					"Total": "352.8",
					"TransitDays": "1",
					"Type": "parcel"
				}
			]
		');

		$this->mockApiResponse = Json::decode('{
			"serviceRecommendationList": {
	        "lowestCostCarrierNumber": "USPS",
	        "lowestCostCarrierClassOfServiceCode": "First Class",
	        "lowestCostConsigneeFreight": 5.976,
	        "lowestCostArrivalDateText": "TUE - 2/1/2022",
	        "lowestCostTransitTime": 3,
	        "fastestCarrierNumber": "UPS",
	        "fastestCarrierClassOfServiceCode": "Next Day Air Saver",
	        "fastestConsigneeFreight": 32.544,
	        "fastestArrivalDateText": "FRI - 1/28/2022",
	        "fastestTransitTime": 1
	    },
	    "ratingResultsList": [
	        {
	            "carrierNumber": "UPS",
	            "carrierClassOfServiceCode": "Next Day Air Saver",
	            "carrierClassOfServiceCodeDescription": "UPS Next Day Air Saver",
	            "shipMode": "Parcel",
	            "rateSystem": "UPS",
	            "statusMessage": "",
	            "consignorFreight": 27.12,
	            "consigneeFreight": 32.544,
	            "listFreight": 96.46,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "WED - 1/26/2022",
	            "transitTime": 1,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "UPS",
	            "carrierClassOfServiceCode": "Next Day Air",
	            "carrierClassOfServiceCodeDescription": "UPS Next Day Air",
	            "shipMode": "Parcel",
	            "rateSystem": "UPS",
	            "statusMessage": "",
	            "consignorFreight": 30.26,
	            "consigneeFreight": 36.312,
	            "listFreight": 109.04,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "WED - 1/26/2022",
	            "transitTime": 1,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "UPS",
	            "carrierClassOfServiceCode": "3 Day Select",
	            "carrierClassOfServiceCodeDescription": "UPS 3 Day Select",
	            "shipMode": "Parcel",
	            "rateSystem": "UPS",
	            "statusMessage": "",
	            "consignorFreight": 19.78,
	            "consigneeFreight": 23.736,
	            "listFreight": 39.53,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "FRI - 1/28/2022",
	            "transitTime": 3,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "UPS",
	            "carrierClassOfServiceCode": "Ground",
	            "carrierClassOfServiceCodeDescription": "UPS Ground",
	            "shipMode": "Parcel",
	            "rateSystem": "UPS",
	            "statusMessage": "",
	            "consignorFreight": 14.56,
	            "consigneeFreight": 17.472,
	            "listFreight": 18.69,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "MON - 1/31/2022",
	            "transitTime": 4,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "UPS",
	            "carrierClassOfServiceCode": "Next Day Air Early A.M.",
	            "carrierClassOfServiceCodeDescription": "UPS Next Day Air Early A.M.",
	            "shipMode": "Parcel",
	            "rateSystem": "UPS",
	            "statusMessage": "",
	            "consignorFreight": 142.79,
	            "consigneeFreight": 171.348,
	            "listFreight": 142.79,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "WED - 1/26/2022",
	            "transitTime": 1,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "UPS",
	            "carrierClassOfServiceCode": "2nd Day Air",
	            "carrierClassOfServiceCodeDescription": "UPS 2nd Day Air",
	            "shipMode": "Parcel",
	            "rateSystem": "UPS",
	            "statusMessage": "",
	            "consignorFreight": 22.06,
	            "consigneeFreight": 26.472,
	            "listFreight": 45.75,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "THU - 1/27/2022",
	            "transitTime": 2,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "USPS",
	            "carrierClassOfServiceCode": "First Class",
	            "carrierClassOfServiceCodeDescription": "First Class",
	            "shipMode": "Parcel",
	            "rateSystem": "Parcel",
	            "statusMessage": "",
	            "consignorFreight": 4.98,
	            "consigneeFreight": 5.976,
	            "listFreight": 4.98,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "FRI - 1/28/2022",
	            "transitTime": 3,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "USPS",
	            "carrierClassOfServiceCode": "Parcel Select",
	            "carrierClassOfServiceCodeDescription": "Parcel Select",
	            "shipMode": "Parcel",
	            "rateSystem": "Parcel",
	            "statusMessage": "",
	            "consignorFreight": 9.03,
	            "consigneeFreight": 10.836,
	            "listFreight": 9.03,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "THU - 2/3/2022",
	            "transitTime": 7,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "USPS",
	            "carrierClassOfServiceCode": "Priority Express",
	            "carrierClassOfServiceCodeDescription": "Priority Express",
	            "shipMode": "Parcel",
	            "rateSystem": "Parcel",
	            "statusMessage": "",
	            "consignorFreight": 42.15,
	            "consigneeFreight": 50.58,
	            "listFreight": 42.15,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "THU - 1/27/2022",
	            "transitTime": 2,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "USPS",
	            "carrierClassOfServiceCode": "Priority Mail",
	            "carrierClassOfServiceCodeDescription": "Priority Mail",
	            "shipMode": "Parcel",
	            "rateSystem": "Parcel",
	            "statusMessage": "",
	            "consignorFreight": 9.68,
	            "consigneeFreight": 11.616,
	            "listFreight": 9.68,
	            "totalServiceFees": 0.0,
	            "fuelSurcharge": 0.0,
	            "currencyCode": "USD",
	            "arrivalDateText": "THU - 1/27/2022",
	            "transitTime": 2,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "ODFL",
	            "carrierClassOfServiceCode": "Standard",
	            "carrierClassOfServiceCodeDescription": "Standard Service",
	            "shipMode": "LTL",
	            "rateSystem": "LTL",
	            "statusMessage": "",
	            "consignorFreight": 273.16,
	            "consigneeFreight": 327.792,
	            "listFreight": 1985.29,
	            "totalServiceFees": 30.0,
	            "fuelSurcharge": 52.92,
	            "currencyCode": "USD",
	            "arrivalDateText": "TUE - 2/1/2022",
	            "transitTime": 5,
	            "shipCodeXRef": null
	        }, {
	            "carrierNumber": "ODFL",
	            "carrierClassOfServiceCode": "Guaranteed",
	            "carrierClassOfServiceCodeDescription": "Guaranteed",
	            "shipMode": "LTL",
	            "rateSystem": "LTL",
	            "statusMessage": "",
	            "consignorFreight": 337.07,
	            "consigneeFreight": 404.484,
	            "listFreight": 1966.28,
	            "totalServiceFees": 93.91,
	            "fuelSurcharge": 52.92,
	            "currencyCode": "USD",
	            "arrivalDateText": "TUE - 2/1/2022",
	            "transitTime": 5,
	            "shipCodeXRef": null
	        }
	    ]
	  }');
	}

	/**
	 * @param bool $includeLineItem
	 * @param bool $includeAddresses
	 * @return MockOrder
	 */
	public function createMockOrder(bool $includeLineItem = true, bool $includeAddresses = true): MockOrder {
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

		$user->setGroups([
			$userGroup1,
		]);

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
			$product1->colorProductLinesCategory = [
				$category1,
			];
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

			$product1->variants = [
				$variant1,
			];

			$product2->variants = [
				$variant2,
			];

			$product3->variants = [
				$variant3,
			];

			$product4->variants = [
				$variant4,
			];

			$product5->variants = [
				$variant5,
			];

			$product6->variants = [
				$variant6,
			];

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
			$address->zipCode = '10001';
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
class MockOrder {
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

	public function __construct() {}

	/**
	 * @return array
	 */
	public function getLineItems(): array {
		return $this->lineItems;
	}

	/**
	 * @return bool
	 */
	public function hasLineItems(): bool {
		return count($this->lineItems) > 0;
	}

	/**
	 * @return float
	 */
	public function getTotalWeight(): float {
		return $this->totalWeight;
	}

	/**
	 * @param $key
	 * @param $value
	 * @return void
	 */
	public function clearNotices($key, $value): void {}

	/**
	 * @param $notice
	 * @return void
	 */
	public function addNotice($notice): void {}
}

class MockCategoryGroup {
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

	public function __construct() {}
}

class MockCategory {
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

	public function __construct() {}
}

class MockProductType {
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

	public function __construct() {}
}

class MockProduct {
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

	public function __construct() {}
}

class MockVariant {
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

	public function __construct() {}
}

class MockLineItem {
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

	public function __construct() {}
}

class MockAddress {
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
	public string $zipCode;

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

	public function __construct() {}
}

class MockAddressState {
	/**
	 * @var int
	 */
	public int $id;

	/**
	 * @var string
	 */
	public string $abbreviation;

	public function __construct() {}
}

class MockUser {
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

	public function __construct() {}

	/**
	 * @param $group
	 * @return bool
	 */
	public function isInGroup($group): bool {
		if (Craft::$app->getEdition() !== Craft::Pro) {
			return false;
		}

		if (is_object($group) && $group instanceof MockUserGroup) {
			$group = $group->id;
		}

		if (is_numeric($group)) {
			return in_array($group, ArrayHelper::getColumn($this->getGroups(), 'id'), false);
		}

		return in_array($group, ArrayHelper::getColumn($this->getGroups(), 'handle'), true);
	}

	/**
	 * @param $groups
	 * @return void
	 */
	public function setGroups($groups): void {
		$this->groups = $groups;
	}

	/**
	 * @return array
	 */
	public function getGroups(): array {
		return $this->groups;
	}
}

class MockUserGroup {
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

	public function __construct() {}
}

