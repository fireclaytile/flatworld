<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\base\Component;
use craft\commerce\models\OrderNotice;
use craft\helpers\Json;
use fireclaytile\flatworld\Flatworld as FlatworldPlugin;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\models\ShippingRate;
use fireclaytile\flatworld\services\Logger;
use fireclaytile\flatworld\services\Mailer;
use fireclaytile\flatworld\services\RatesApi;
use fireclaytile\flatworld\services\ShippingRates;
use fireclaytile\flatworld\services\salesforce\models\ShippingRequest;
use fireclaytile\flatworld\services\salesforce\models\LineItem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use verbb\postie\Postie;
use verbb\postie\helpers\PostieHelper;
use verbb\postie\events\ModifyRatesEvent;
use yii\base\InvalidConfigException;

/**
 * Service class Rates.
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services
 */
class Rates extends Component
{
    /**
     * @var FlatworldProvider
     */
    protected FlatworldProvider $flatworld;

    private RatesApi $_ratesApiService;

    /**
     * The Commerce order object.
     *
     * @var mixed
     */
    private $_order;

    /**
     * @var array
     */
    private array $_pieces;

    /**
     * @var float
     */
    private float $_totalWeight;

    /**
     * The response from the API call for rates.
     *
     * @var mixed
     */
    private $_response;

    /**
     * ShippingRequest object.
     *
     * @var ShippingRequest
     */
    private ShippingRequest $_shippingRequest;

    /**
     * @var bool
     */
    private bool $_orderContainsMerchandise;

    /**
     * @var bool
     */
    private bool $_orderContainsSampleProducts;

    /**
     * @var bool
     */
    private bool $_orderContainsStandardProducts;

    /**
     * @var boolean
     */
    private bool $_loggingEnabled;

    /**
     * Rates constructor.
     */
    function __construct($loggingEnabled = false, $settings = null)
    {
        $this->_loggingEnabled = $loggingEnabled;

        $this->flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');

        if ($settings) {
            $this->flatworld->settings = $settings;
        }
    }

    /**
     * @param $order
     * @return array
     * @throws GuzzleException
     * @throws InvalidConfigException
     */
    public function getRates($order): array
    {
        $this->setOrder($order);

        if (!$this->validateOrder($this->_order)) {
            return $this->_modifyRatesEvent([], $this->_order);
        }

        $this->filterOutAddons();
        $this->countProductTypes();
        $this->setPieces();
        $this->setTotalWeight();

        // ensure there is a weight.
        if (!$this->checkTotalWeight()) {
            return $this->_modifyRatesEvent([], $this->_order);
        }

        // ensure the weight is within the limit.
        if (!$this->checkWeightLimit()) {
            return $this->_modifyRatesEvent([], $this->_order);
        }

        $this->setShippingRequest();

        // Lets check the rates cache before making an API request - this will be an array or be false
        $ratesCache = $this->getRatesCache();

        if (!empty($ratesCache) && is_array($ratesCache)) {
            return $this->_modifyRatesEvent($ratesCache, $this->_order);
        }

        $this->requestRates();

        $rates = $this->responseRates();

        // Lets cache the rates
        $this->setRatesCache($rates);

        return $this->_modifyRatesEvent($rates, $this->_order);
    }

    /**
     * Sets the _order property.
     *
     * @param $order
     * @return void
     */
    public function setOrder($order): void
    {
        $this->_order = clone $order;
    }

    /**
     * Gets the _order property.
     *
     * @return void
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * Validates the order.
     *
     * @param mixed $order
     * @return boolean
     */
    public function validateOrder($order): bool
    {
        $this->setOrder($order);

        if (!$this->checkLineItems()) {
            return false;
        }

        if (!$this->checkLineItemRequiredFields()) {
            return false;
        }

        if (!$this->checkShippingAddress()) {
            return false;
        }

        return true;
    }

    /**
     * Checks for presence of line items on the order.
     *
     * @return bool
     */
    public function checkLineItems(): bool
    {
        if (!$this->_order->hasLineItems()) {
            $this->_logMessage(
                __METHOD__,
                'Has no line items yet, bailing out',
            );

            return false;
        }

        $this->_logMessage(__METHOD__, 'We have a line items, continuing');

        return true;
    }

    /**
     * Checks for presence of required fields on the line items. If any are missing, an email is sent to the admin.
     *
     * @return bool
     */
    public function checkLineItemRequiredFields(): bool
    {
        $problems = false;

        $problemMessage = '';

        foreach ($this->_order->lineItems as $item) {
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

                        $problemMessage .= "\nOrderID: {$this->_order->id}, Product URL: {$item->purchasable->url}, Issue: Missing dimensions and/or weight";

                        $this->_logMessage(
                            __METHOD__,
                            "Required Fields Missing. Order ID: {$this->_order->id}, Product ID: {$item->purchasable->product->id}",
                        );
                    }
                }
            }
        }

        if ($problems && !empty($problemMessage)) {
            $this->_logMessage(
                __METHOD__,
                'Invalid line items found, bailing out...',
            );

            if ($this->_getSetting('enableErrorEmailMessages')) {
                $this->_logMessage(__METHOD__, 'Sending email with error.');
                FlatworldPlugin::getInstance()->mailer->sendMail(
                    $problemMessage,
                );
            }

            return false;
        }

        $this->_logMessage(
            __METHOD__,
            'We have a valid line items, continuing',
        );

        return true;
    }

    /**
     * Checks to see if the order has a shipping address.
     *
     * @return bool
     */
    public function checkShippingAddress(): bool
    {
        if (
            empty($this->_order->shippingAddress) ||
            empty($this->_order->shippingAddress->zipCode)
        ) {
            $this->_logMessage(
                __METHOD__,
                'Has no shipping address yet, bailing out',
            );

            return false;
        }

        $this->_logMessage(
            __METHOD__,
            'We have a shipping address, continuing',
        );

        return true;
    }

    /**
     * Filters out addons from the order's lineItems.
     *
     * @return void
     */
    public function filterOutAddons(): void
    {
        $this->_order->lineItems = array_filter(
            $this->_order->lineItems,
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
     * @return void
     */
    public function filterOutMerchandise(): void
    {
        $this->_order->lineItems = array_filter(
            $this->_order->lineItems,
            function ($item) {
                $isMerchandise =
                    !empty($item->purchasable) &&
                    !empty($item->purchasable->product) &&
                    !empty($item->purchasable->product->type) &&
                    !empty($item->purchasable->product->type->handle) &&
                    $item->purchasable->product->type->handle === 'merchandise';

                return !$isMerchandise;
            },
        );
    }

    /**
     * @return void
     */
    public function filterOutSampleProducts(): void
    {
        $this->_order->lineItems = array_filter(
            $this->_order->lineItems,
            function ($item) {
                $isSample = isset($item->options['sample']);

                return !$isSample;
            },
        );
    }

    /**
     * @return void
     */
    public function filterOutStandardProducts(): void
    {
        $this->_order->lineItems = array_filter(
            $this->_order->lineItems,
            function ($item) {
                $isSample = isset($item->options['sample']);
                $isAddOn =
                    !empty($item->purchasable) &&
                    !empty($item->purchasable->product) &&
                    !empty($item->purchasable->product->type) &&
                    !empty($item->purchasable->product->type->handle) &&
                    $item->purchasable->product->type->handle === 'addons';
                $isMerchandise =
                    !empty($item->purchasable) &&
                    !empty($item->purchasable->product) &&
                    !empty($item->purchasable->product->type) &&
                    !empty($item->purchasable->product->type->handle) &&
                    $item->purchasable->product->type->handle === 'merchandise';

                return $isSample || $isAddOn || $isMerchandise;
            },
        );
    }

    /**
     * Determines how many of each product type are in the order.
     *
     * @return void
     */
    public function countProductTypes(): void
    {
        $this->_logMessage(
            __METHOD__,
            'Order Contains Standard Products to FALSE',
        );
        $this->_logMessage(
            __METHOD__,
            'Order Contains Sample Products to FALSE',
        );
        $this->_logMessage(__METHOD__, 'Order Contains Merchandise to FALSE');

        $this->setOrderContainsStandardProducts(false);
        $this->setOrderContainsSampleProducts(false);
        $this->setOrderContainsMerchandise(false);

        $totalSample = 0;
        $totalStandard = 0;
        $totalMerchandise = 0;

        foreach ($this->_order->lineItems as $item) {
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
            $this->_logMessage(
                __METHOD__,
                'Order Contains Standard Products to TRUE',
            );
            $this->setOrderContainsStandardProducts(true);
        }

        if ($totalSample > 0) {
            $this->_logMessage(
                __METHOD__,
                'Order Contains Sample Products to TRUE',
            );
            $this->setOrderContainsSampleProducts(true);
        }

        if ($totalMerchandise > 0) {
            $this->_logMessage(
                __METHOD__,
                'Order Contains Merchandise to TRUE',
            );
            $this->setOrderContainsMerchandise(true);
        }
    }

    /**
     * Sets the _orderContainsStandardProducts property.
     *
     * @param bool $orderContainsStandardProducts
     * @return void
     */
    public function setOrderContainsStandardProducts(
        bool $orderContainsStandardProducts,
    ): void {
        $this->_orderContainsStandardProducts = $orderContainsStandardProducts;
    }

    /**
     * Gets the _orderContainsStandardProducts property.
     *
     * @return bool
     */
    public function orderContainsStandardProducts(): bool
    {
        return $this->_orderContainsStandardProducts;
    }

    /**
     * Sets the _orderContainsSampleProducts property.
     *
     * @param $orderContainsSampleProducts
     * @return void
     */
    public function setOrderContainsSampleProducts(
        $orderContainsSampleProducts,
    ): void {
        $this->_orderContainsSampleProducts = $orderContainsSampleProducts;
    }

    /**
     * Gets the _orderContainsSampleProducts property.
     *
     * @return bool
     */
    public function orderContainsSampleProducts(): bool
    {
        return $this->_orderContainsSampleProducts;
    }

    /**
     * Sets the _orderContainsMerchandise property.
     *
     * @param $orderContainsMerchandise
     * @return void
     */
    public function setOrderContainsMerchandise($orderContainsMerchandise): void
    {
        $this->_orderContainsMerchandise = $orderContainsMerchandise;
    }

    /**
     * Gets the _orderContainsMerchandise property.
     *
     * @return bool
     */
    public function orderContainsMerchandise(): bool
    {
        return $this->_orderContainsMerchandise;
    }

    /**
     * Sets the _pieces property which is used to calculate total weight.
     *
     * @return void
     */
    public function setPieces(): void
    {
        $this->_pieces = [];

        foreach ($this->_order->lineItems as $item) {
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

                $this->_logMessage(
                    __METHOD__,
                    'Product Category: ' . $colorProductLinesCategorySlug,
                );
                $this->_logMessage(
                    __METHOD__,
                    'Product Type: ' .
                        $item->purchasable->product->type->handle,
                );

                $weightPerSquareFootPerProductTypes = $this->_getSetting(
                    'weightPerSquareFoot',
                );

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
                        $this->_logMessage(
                            __METHOD__,
                            'Found Product with Product Type, Product Line and Weight Per Square Feet',
                        );

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
                            $this->_logMessage(
                                __METHOD__,
                                'Found Product with Product Type but Product Line is Handpainted',
                            );

                            break;

                            // Since we DONT have a product line slug, we are only
                            // checking if our product type == x
                            // Example: Brick
                        } elseif (
                            $item->purchasable->product->type->handle ===
                            $productTypeHandle
                        ) {
                            $this->_logMessage(
                                __METHOD__,
                                'Found Product with Product Type and Weight Per Square Feet',
                            );

                            $weightPerSquareFoot = $value;
                            break;
                        }
                    }
                }

                if (!empty($weightPerSquareFoot)) {
                    $this->_pieces[] = $this->calculatePieces(
                        $weightPerSquareFoot,
                        $item->weight,
                        $item->qty,
                    );

                    $this->_logMessage(
                        __METHOD__,
                        'Weight Per Square Foot: ' .
                            $weightPerSquareFoot .
                            ' (' .
                            floatval($weightPerSquareFoot) .
                            ')',
                    );
                } else {
                    $this->_pieces[] = intval($item->qty);
                    $this->_logMessage(__METHOD__, 'No Weight Per Square Foot');
                }

                $this->_logMessage(
                    __METHOD__,
                    'Item Weight: ' .
                        $item->weight .
                        ' (' .
                        floatval($item->weight) .
                        ')',
                );
                $this->_logMessage(
                    __METHOD__,
                    'Item Qty: ' . $item->qty . ' (' . intval($item->qty) . ')',
                );
                $this->_logMessage(
                    __METHOD__,
                    'Item Pieces: ' . Json::encode($this->_pieces),
                );
            } else {
                $this->_pieces[] = intval($item->qty);

                $this->_logMessage(__METHOD__, 'No Purchase Item Found');
                $this->_logMessage(
                    __METHOD__,
                    'Item Weight: ' .
                        $item->weight .
                        ' (' .
                        floatval($item->weight) .
                        ')',
                );
                $this->_logMessage(
                    __METHOD__,
                    'Item Qty: ' . $item->qty . ' (' . intval($item->qty) . ')',
                );
                $this->_logMessage(
                    __METHOD__,
                    'Item Pieces: ' . Json::encode($this->_pieces),
                );
            }
        }
    }

    /**
     * Calculates the number of pieces for a given line item based on qty (sq ft) and variant weight.
     *
     * Example: (4.5 / 0.69) * 25 = 163 (breakdown equals weight per sq ft / variant weight) * sq ft = number of pieces
     *
     * @param $weightPerSquareFoot
     * @param $weight
     * @param $qty
     * @return false|float
     */
    public function calculatePieces($weightPerSquareFoot, $weight, $qty)
    {
        return ceil(
            (floatval($weightPerSquareFoot) / floatval($weight)) * intval($qty),
        );
    }

    /**
     * Gets the _pieces property.
     *
     * @return array
     */
    public function getPieces(): array
    {
        return $this->_pieces;
    }

    /**
     * Sets the _totalWeight property.
     *
     * @param float $totalWeight
     * @return void
     */
    public function setTotalWeight(float $totalWeight = 0.0): void
    {
        $this->_totalWeight = 0.0;

        if ($totalWeight > 0.0) {
            $this->_totalWeight = $totalWeight;
        } else {
            $index = 0;

            foreach ($this->_order->lineItems as $item) {
                $this->_totalWeight +=
                    floatval($item->weight) * $this->_pieces[$index];

                $index++;
            }
        }

        $this->_totalWeight = number_format($this->_totalWeight, 2, '.', '');

        $this->_logMessage(__METHOD__, 'Total Weight: ' . $this->_totalWeight);
        $this->_logMessage(
            __METHOD__,
            'Total Max Weight: ' . $this->_getSetting('totalMaxWeight'),
        );
    }

    /**
     * Checks to see if the total weight of the order is greater than zero. If
     * it is, return true. If it is not, return false.
     *
     * @return bool
     */
    public function checkTotalWeight(): bool
    {
        if ($this->getTotalWeight() <= 0.0) {
            $this->_logMessage(
                __METHOD__,
                'Total weight was zero, bailing out',
            );

            return false;
        }

        $this->_logMessage(__METHOD__, 'Total weight found. Continuing');

        return true;
    }

    /**
     * Gets the _totalWeight property.
     *
     * @return float
     */
    public function getTotalWeight(): float
    {
        return $this->_totalWeight;
    }

    /**
     * Check to see if the total weight of the order exceeds the weight limit
     * set in the plugin settings. If the weight is within the limit, return
     * true. If the weight exceeds the limit, return false and add a notice to
     * the order.
     *
     * @return bool
     * @throws InvalidConfigException
     */
    public function checkWeightLimit(): bool
    {
        $this->_order->clearNotices(
            'shippingMethodChanged',
            'shippingWeightLimit',
        );

        if ($this->weightLimitReached()) {
            $this->_logMessage(__METHOD__, 'Weight limit reached');

            $notice = Craft::createObject([
                'class' => OrderNotice::class,
                'type' => 'shippingMethodChanged',
                'attribute' => 'shippingWeightLimit',
                'message' => $this->_getSetting('weightLimitMessage'),
            ]);

            $this->_order->addNotice($notice);

            return false;
        }

        return true;
    }

    /**
     * Calculates id the total weight of the order exceeds the weight limit.
     * Returns true if it does, false if it does not.
     *
     * @return bool
     */
    public function weightLimitReached(): bool
    {
        return !empty($this->_getSetting('totalMaxWeight')) &&
            $this->getTotalWeight() >
                floatval($this->_getSetting('totalMaxWeight'));
    }

    /**
     * Determines the productId for an order line item.
     *
     * @param mixed $row A LineItem from the order.
     * @return string
     */
    private function _setLineItemProductId($row): string
    {
        // This is taken directly from the fct Salesforce plugin.
        if (
            isset($row->options['masterSalesforceId']) and
            empty($row->options['masterSalesforceId'])
        ) {
            return '';
        }

        $commerceService = craft\commerce\Plugin::getInstance();

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

    /**
     * Sets the _shippingRequest property based on the order details.
     *
     * @return void
     */
    public function setShippingRequest(): void
    {
        $order = $this->getOrder();

        $this->_shippingRequest = new ShippingRequest(
            $order->shippingAddress->zipCode,
            false,
            'Sample',
            [],
        );

        foreach ($order->lineItems as $item) {
            $this->_shippingRequest->addLineItem(
                new LineItem($this->_setLineItemProductId($item), $item->qty),
            );
        }

        if ($this->orderContainsStandardProducts()) {
            $this->_shippingRequest->orderType = 'Order';
        }

        if (
            $order->truckLiftCharge &&
            $this->_getSetting('enableLiftGateRates')
        ) {
            $this->_shippingRequest->liftGate = true;
        }
    }

    /**
     * Gets the _shippingRequest property.
     *
     * @return ShippingRequest
     */
    public function getShippingRequest(): ShippingRequest
    {
        return $this->_shippingRequest;
    }

    /**
     * Tests the connection to the Rates API.
     *
     * @return boolean
     */
    public function testRatesConnection(): bool
    {
        $this->_logMessage(__METHOD__, 'Testing Rates API Connection');

        $this->_ratesApiService = FlatworldPlugin::getInstance()->ratesApi;
        $result = $this->_ratesApiService->salesforceConnect();

        $this->_logMessage(__METHOD__, 'Rates API Connection: ' . $result);

        return $result;
    }

    /**
     * Make a request to the API for Rates.
     *
     * @return void
     * @throws GuzzleException
     */
    public function requestRates()
    {
        $shippingRequest = $this->_shippingRequest;
        $body = Json::encode($shippingRequest);

        $this->_logMessage(__METHOD__, 'ShippingRequest: ' . $body);

        $this->_ratesApiService = FlatworldPlugin::getInstance()->ratesApi;
        $response = $this->_ratesApiService->getRates($shippingRequest);

        $this->setResponse($response);
    }

    /**
     * Sets the _response property.
     *
     * @param $response
     * @return void
     */
    public function setResponse($response): void
    {
        $this->_logMessage(__METHOD__, 'Response: ' . JSON::encode($response));
        $this->_response = $response;
    }

    /**
     * Gets the _response property.
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Returns an array containing the cheapest and/or quickest shipping rate
     * options.
     *
     * @return array
     */
    public function responseRates(): array
    {
        $response = $this->getResponse();
        $shippingRates = new ShippingRates($response);

        $cheapestRate = $shippingRates->getCheapestRate();
        $quickestRate = $shippingRates->getFastestRate();
        if (empty($cheapestRate) && empty($quickestRate)) {
            $this->_logMessage(
                __METHOD__,
                'Didnt find any cheapest and fastest carrier details, so returning empty-handed.',
            );

            return [];
        }

        $this->_logMessage(
            __METHOD__,
            'Found cheapest and fastest rates and carrier details',
        );

        $cheapestServiceHandle = $cheapestRate
            ? $cheapestRate->getServiceHandle()
            : '';
        $quickestServiceHandle = $quickestRate
            ? $quickestRate->getServiceHandle()
            : '';

        $rates = [];

        $handles = array_keys($this->getServiceList());

        $foundServiceHandle = false;

        // Set amounts for the cheapest and quickest options
        foreach ($handles as $handle) {
            if ($handle === $cheapestServiceHandle) {
                $foundServiceHandle = true;

                $rates[$handle][
                    'arrival'
                ] = $cheapestRate->getArrivalEstimationString();
                $rates[$handle]['transitTime'] = $cheapestRate->transitDays;
                $rates[$handle]['arrivalDateText'] =
                    $cheapestRate->estimatedDeliveryDate;
                $rates[$handle]['amount'] = $cheapestRate->total;
            }

            if ($handle === $quickestServiceHandle) {
                $foundServiceHandle = true;

                $rates[$handle][
                    'arrival'
                ] = $quickestRate->getArrivalEstimationString();
                $rates[$handle]['transitTime'] = $quickestRate->transitDays;
                $rates[$handle]['arrivalDateText'] =
                    $quickestRate->estimatedDeliveryDate;
                $rates[$handle]['amount'] = $quickestRate->total;
            }
        }

        if (!$foundServiceHandle) {
            $this->_logMessage(
                __METHOD__,
                'Didnt find any matching carrier handles',
            );
        }

        // Apply flat rate shipping for samples only orders: https://app.asana.com/0/1200248609605430/1201862416773959/f
        if (
            !$this->orderContainsStandardProducts() &&
            !$this->orderContainsMerchandise() &&
            $this->orderContainsSampleProducts() &&
            $foundServiceHandle
        ) {
            $rates = $this->setFlatRate($rates);

            $this->_logMessage(
                __METHOD__,
                'Applied Flat Rate Shipping Carrier',
            );
        }

        // Sort so the lowest cost carrier is first/default
        $amount = array_column($rates, 'amount');
        array_multisort($amount, SORT_ASC, $rates);

        // Shipping for samples only orders for trade is $0 and $8 for everyone else
        // (We set this on the lowest cost carrier which also overrides flat rate cost)
        if (
            !$this->orderContainsStandardProducts() &&
            !$this->orderContainsMerchandise() &&
            $this->orderContainsSampleProducts()
        ) {
            $firstCarrier = array_slice($rates, 0, 1);
            $firstServiceHandle = key($firstCarrier);

            if (
                !empty($this->_order->user) &&
                $this->_order->user->isInGroup('customersTrade15')
            ) {
                $rates[$firstServiceHandle]['amount'] = 0;
                $this->_logMessage(
                    __METHOD__,
                    'Applied Free Shipping on Samples only for Trade account',
                );
            } else {
                $rates[$firstServiceHandle][
                    'amount'
                ] = $this->getFlatRateAmount();
                $this->_logMessage(
                    __METHOD__,
                    'Applied $8 flat rate Shipping on Samples only for samples only orders',
                );
            }
        }

        return $this->_modifyRatesEvent($rates, $this->_order);
    }

    /**
     * Sets the flat-rate shipping price on rates.
     *
     * @param array $rates
     * @return array
     */
    public function setFlatRate(array $rates): array
    {
        $flatRateHandle = $this->getFlatRateHandle();
        $flatRateAmount = $this->getFlatRateAmount();

        $flatRateCarrier = [];

        // Remove any carrier less than flat rate carrier
        foreach ($rates as $handle => $rate) {
            if ($rate['amount'] < $flatRateAmount) {
                // Grab a copy first so we can use its info like arrival time
                $flatRateCarrier = array_merge($rate);
                $flatRateCarrier['amount'] = $flatRateAmount;

                unset($rates[$handle]);
            }
        }

        // Inject flat rate carrier into the rates list
        if (!empty($flatRateCarrier)) {
            $rates[$flatRateHandle] = $flatRateCarrier;
        }

        return $rates;
    }

    /**
     * Gets the flat rate carrier handle based from the plugin settings.
     *
     * @return string
     */
    public function getFlatRateHandle(): string
    {
        $handle = str_replace(
            ' ',
            '_',
            strtoupper($this->_getSetting('flatRateCarrierName')),
        );
        return $handle;
    }

    /**
     * Gets the flat rate amount from the plugin settings.
     *
     * @return float
     */
    public function getFlatRateAmount(): float
    {
        $flatRateAmount = number_format(
            $this->_getSetting('flatRateCarrierCost'),
            2,
            '.',
            ',',
        );

        return floatval($flatRateAmount);
    }

    /**
     * Looks for the presence of cached rate data and returns it if found. Returns false if not.
     *
     * @return mixed
     */
    public function getRatesCache(): mixed
    {
        $cacheKey = $this->_getCacheKey();

        $this->_logMessage(__METHOD__, 'Rates cache key :: ' . $cacheKey);

        if (!Craft::$app->cache->exists($cacheKey)) {
            $this->_logMessage(__METHOD__, 'Rates cache did not exist');

            return false;
        }

        $this->_logMessage(__METHOD__, 'Rates cache found');

        return Craft::$app->cache->get($cacheKey);
    }

    /**
     * Creates a rates cache and sets it to expire in 5 minutes.
     *
     * @param array $rates
     * @return void
     */
    public function setRatesCache(array $rates): void
    {
        // Duration in minutes * seconds
        $duration = 5 * 60;

        $cacheKey = $this->_getCacheKey();

        Craft::$app->cache->set($cacheKey, $rates, $duration);

        $this->_logMessage(__METHOD__, 'Set rates cache to expire in 5 mins');
    }

    /**
     * Creates a cache key based on order details.
     *
     * @return array|false|string
     */
    private function _getCacheKey(): array|false|string
    {
        $order = $this->getOrder();

        if (!$order) {
            $this->_logMessage(__METHOD__, 'Order was null');

            return false;
        }

        $shippingRequest = $this->getShippingRequest();

        if (!$shippingRequest || !$shippingRequest->zipCode) {
            $this->_logMessage(__METHOD__, 'Shipping Request was null');
            return false;
        }

        $prefix = $shippingRequest->liftGate ? 'flatworld-lg' : 'flatworld';

        $cacheKey = PostieHelper::getSignature($order, $prefix);

        return $cacheKey;
    }

    /**
     * Modify rates on order and return rates
     *
     * @param array $rates
     * @param $order
     * @return array
     */
    private function _modifyRatesEvent(array $rates, $order): array
    {
        return $this->flatworld->modifyRatesEvent($rates, $order);
    }

    /**
     * Gets the carrierClassOfServices setting.
     *
     * @return array
     */
    public function getServiceList(): array
    {
        return $this->flatworld->getServiceList();
    }

    private function _getSetting($key)
    {
        return $this->flatworld->getSetting($key);
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

        $logger = new Logger(true);
        return $logger->logMessage($msg);
    }
}
