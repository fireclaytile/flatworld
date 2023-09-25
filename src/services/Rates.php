<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\base\Component;
use craft\commerce\models\OrderNotice;
use craft\helpers\Json;
use fireclaytile\flatworld\Flatworld as FlatworldPlugin;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\services\Logger;
use fireclaytile\flatworld\services\Mailer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use verbb\postie\Postie;
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
     * Array of package details.
     *
     * TODO: Determine if this is still needed. It's a holdover from the Pacejet plugin
     *
     * @var array
     */
    private array $_packageDetailsList;

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
     * The handle of the quickest carrier.
     *
     * @var string
     */
    private string $_quickestServiceHandle;

    /**
     * The handle of the cheapest carrier.
     *
     * @var string
     */
    private string $_cheapestServiceHandle;

    /**
     * The quickest rate information.
     *
     * @var array|null
     */
    private ?array $_quickestRate;

    /**
     * The cheapest rate information.
     *
     * @var array|null
     */
    private ?array $_cheapestRate;

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

        if (!$this->checkTotalWeight()) {
            return $this->_modifyRatesEvent([], $this->_order);
        }

        if (!$this->checkWeightLimit()) {
            return $this->_modifyRatesEvent([], $this->_order);
        }

        $this->setPackageDetailsList();

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
                'Invalid line items found, bailing out and sending email',
            );

            FlatworldPlugin::getInstance()->mailer->sendMail($problemMessage);

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
     * Sets the _packageDetailsList property depending on if the order contains
     * standard products, sample products, or merch.
     *
     * TODO: Determine how much (if any) of this is still needed. It's a holdover from the Pacejet plugin.
     *
     * @return void
     */
    public function setPackageDetailsList(): void
    {
        // There is also a threshold of 150 lbs. After that threshold, Flatworld
        // seems to treat both Single box packing and Pallet as the "same".

        // If an order contains standard products, its over weight threshold -
        // doesnt make a difference if it contains samples and or merch - its
        // over weight threshold
        if (
            $this->orderContainsStandardProducts() &&
            !$this->underWeightThreshold()
        ) {
            $this->setPallet();
        }

        // If an order contains standard products, its under weight threshold -
        // doesnt make a difference if it contains samples and or merch - has to
        // use a single box
        elseif (
            $this->orderContainsStandardProducts() &&
            $this->underWeightThreshold()
        ) {
            $this->setSingleBox();
        }

        // If an order contains sample products or merch, it has to use a single box
        elseif (
            $this->orderContainsSampleProducts() ||
            $this->orderContainsMerchandise()
        ) {
            $this->setSingleBox();
        }

        // There is an issue...Should never get this far!
        else {
            $this->_logMessage(__METHOD__, 'No packing method was set');
        }
    }

    /**
     * Gets the _packageDetailsList property.
     *
     * @return array
     */
    public function getPackageDetailsList(): array
    {
        return $this->_packageDetailsList;
    }

    /**
     * Determines if the Total Weight of the order is under the weight threshold.
     *
     * @return bool
     */
    public function underWeightThreshold(): bool
    {
        return $this->getTotalWeight() <=
            floatval($this->_getSetting('weightThreshold'));
    }

    /**
     * Sets the _packageDetailsList property to an array containing a single box
     * name and total weight.
     *
     * TODO: Determine if this is still needed. It's a holdover from the Pacejet
     * plugin.
     *
     * @return void
     */
    public function setSingleBox(): void
    {
        $this->_packageDetailsList = [
            [
                'Name' => 'SingleBox',
                'Weight' => $this->getTotalWeight(),
            ],
        ];

        $this->_logMessage(
            __METHOD__,
            Json::encode($this->_packageDetailsList),
        );
    }

    /**
     * Sets the _packageDetailsList property to an array containing a pallet
     * name and total weight.
     *
     * TODO: Determine if this is still needed. It's a holdover from the Pacejet
     * plugin.
     *
     * @return void
     */
    public function setPallet(): void
    {
        $this->_packageDetailsList = [
            [
                'PackageNumber' => 'Pallet',
                'Weight' => $this->getTotalWeight(),
            ],
        ];

        $this->_logMessage(
            __METHOD__,
            Json::encode($this->_packageDetailsList),
        );
    }

    /**
     * Returns an array of settings to use as a payload for the API request.
     *
     * @return array
     */
    public function getPayload(): array
    {
        // SF API request body should look somethng like this (when turned into JSON):
        // 	{
        // 	  "ZipCode": "11111",
        // 	  "LineItems": [
        // 	    {
        // 		  "ProductId": "12345",
        // 		  "Quantity": 25
        // 		}
        // 	  ]
        // 	}

        // TODO: Change this return to match the example just above. It's from the Pacejet plugin.
        return [
            'PackageDetailsList' => $this->getPackageDetailsList(),
            'Location' => $this->_getSetting('username'),
            'LicenseID' => $this->_getSetting('licenseId'),
            'UpsLicenseID' => $this->_getSetting('upsLicenseId'),
        ];
    }

    /**
     * Make a request to the API for Rates.
     *
     * @return void
     * @throws GuzzleException
     */
    public function requestRates()
    {
        $payload = $this->getPayload();

        $body = Json::encode($payload);

        $this->_logMessage(__METHOD__, 'Payload: ' . $body);

        // (held over from Pacejet plugin)
        // Not entirely sure we need this but adding it in anyways as other Postie
        // providers seem to use it
        $this->beforeSendPayload($this, $payload, $this->_order);

        $response = $this->_getRequest('POST', 'Rates', [
            'body' => $body,
        ]);

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
        // filter for fastest and cheapest rates
        $this->setQuickestRate();
        $this->setCheapestRate();
        $this->setQuickestServiceHandle();
        $this->setCheapestServiceHandle();

        if (
            !empty($this->getCheapestRate()) &&
            !empty($this->getQuickestRate())
        ) {
            $this->_logMessage(
                __METHOD__,
                'Found cheapest and fastest rates and carrier details',
            );

            $cheapestRate = $this->getCheapestRate();
            $quickestRate = $this->getQuickestRate();
            $cheapestServiceHandle = $this->getCheapestServiceHandle();
            $quickestServiceHandle = $this->getQuickestServiceHandle();

            $rates = [];

            $handles = array_keys($this->getServiceList());

            $foundServiceHandle = false;

            // Set amounts for the cheapest and quickest options
            foreach ($handles as $handle) {
                if ($handle === $cheapestServiceHandle) {
                    $foundServiceHandle = true;

                    $transitTime = $cheapestRate['TransitDays'];
                    $arrivalDateText = $cheapestRate['EstimatedDeliveryDate'];

                    $arrival = $this->getArrival($transitTime);

                    $rates[$handle]['arrival'] = $arrival;
                    $rates[$handle]['transitTime'] = $transitTime;
                    $rates[$handle]['arrivalDateText'] = $arrivalDateText;
                    $rates[$handle]['amount'] = $cheapestRate['Total'];
                }

                if ($handle === $quickestServiceHandle) {
                    $foundServiceHandle = true;

                    $transitTime = $quickestRate['TransitDays'];
                    $arrivalDateText = $quickestRate['EstimatedDeliveryDate'];

                    $arrival = $this->getArrival($transitTime);

                    $rates[$handle]['arrival'] = $arrival;
                    $rates[$handle]['transitTime'] = $transitTime;
                    $rates[$handle]['arrivalDateText'] = $arrivalDateText;
                    $rates[$handle]['amount'] = $quickestRate['Total'];
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

        $this->_logMessage(
            __METHOD__,
            'Didnt find any cheapest and fastest carrier details',
        );

        return [];
    }

    /**
     * Sets the _quickRate property.
     *
     * @return void
     */
    public function setQuickestRate(): void
    {
        $response = $this->getResponse();
        $this->_quickestRate = $this->findQuickestRate($response);
    }

    /**
     * Sets the _cheapestRate property.
     *
     * @return void
     */
    public function setCheapestRate(): void
    {
        $response = $this->getResponse();
        $this->_cheapestRate = $this->findCheapestRate($response);
    }

    /**
     * Gets the _quickestRate property.
     *
     * @return string
     */
    public function getQuickestRate(): ?array
    {
        return $this->_quickestRate;
    }

    /**
     * Gets the _cheapestRate property.
     *
     * @return string
     */
    public function getCheapestRate(): ?array
    {
        return $this->_cheapestRate;
    }

    /**
     * Sets the _quickestServiceHandle property.
     *
     * @return void
     */
    public function setQuickestServiceHandle(): void
    {
        $quickestRate = $this->getQuickestRate();
        if (empty($quickestRate)) {
            return;
        }
        $this->_quickestServiceHandle = $this->getServiceHandle(
            $quickestRate['ServiceLevel'],
        );
    }

    /**
     * Gets the _quickestServiceHandle property.
     *
     * @return string
     */
    public function getQuickestServiceHandle(): string
    {
        return $this->_quickestServiceHandle;
    }

    /**
     * Sets the _cheapestServiceHandle property.
     *
     * @return void
     */
    public function setCheapestServiceHandle(): void
    {
        $cheapestRate = $this->getCheapestRate();
        if (empty($cheapestRate)) {
            return;
        }
        $this->_cheapestServiceHandle = $this->getServiceHandle(
            $cheapestRate['ServiceLevel'],
        );
    }

    /**
     * Gets the _cheapestServiceHandle property.
     *
     * @return string
     */
    public function getCheapestServiceHandle(): string
    {
        return $this->_cheapestServiceHandle;
    }

    /**
     * Converts a "ServiceLevel" into uppercase with underscores in place of
     * spaces.
     *
     * @param string $serviceLevel The service level string to convert
     * @return string
     */
    public function getServiceHandle(string $serviceLevel): string
    {
        return str_replace(' ', '_', strtoupper($serviceLevel));
    }

    /**
     * Find and return the rate with the quickest estimated delivery date and
     * time.
     *
     * @param array $jsonData JSON data containing shipping rate options
     * @param \DateTime $now (optional) The current date and time, default is now
     * @return array|null
     */
    public function findQuickestRate(
        array $jsonData,
        \DateTime $now = null,
    ): ?array {
        if (!$now) {
            $now = new \DateTime();
        }

        $quickestRate = null;
        $minTimeDiff = PHP_INT_MAX;

        foreach ($jsonData as $rate) {
            if (isset($rate['EstimatedDeliveryDate'])) {
                $deliveryDate = \DateTime::createFromFormat(
                    'Y/m/d',
                    $rate['EstimatedDeliveryDate'],
                );

                // make sure delivery date is in the future
                // if ($deliveryDate >= $now && $deliveryDate < $now->add(new \DateInterval('P1D'))) {

                $timeDiff =
                    $deliveryDate->getTimestamp() - $now->getTimestamp();

                // EstimatedDeliveryTime may not exist on the rate result
                // TODO: This doesn't currently work.
                // if (isset($rate['EstimatedDeliveryTime'])) {
                // 	$deliveryTime = \DateTime::createFromFormat('H:i A', $rate['EstimatedDeliveryTime']);
                // 	$timeDiff += $deliveryTime->getTimestamp() - $now->getTimestamp();
                // }

                if ($timeDiff < $minTimeDiff) {
                    $quickestRate = $rate;
                    $minTimeDiff = $timeDiff;
                }
                // }
            }
        }
        $this->_logMessage(
            __METHOD__,
            'returning: ' . Json::encode($quickestRate),
        );
        return $quickestRate;
    }

    /**
     * Find and return the cheapest rate option.
     *
     * @param array $jsonData JSON data containing shipping rate options
     * @return array|null
     */
    public function findCheapestRate(array $jsonData): ?array
    {
        $cheapestRate = null;
        $lowestTotal = PHP_FLOAT_MAX;

        foreach ($jsonData as $rate) {
            if (isset($rate['Total'])) {
                $total = (float) $rate['Total'];

                if ($total < $lowestTotal) {
                    $cheapestRate = $rate;
                    $lowestTotal = $total;
                }
            }
        }
        $this->_logMessage(
            __METHOD__,
            'returning: ' . Json::encode($cheapestRate),
        );
        return $cheapestRate;
    }

    /**
     * Returns a string of the estimated arrival time based on the transit time.
     *
     * @param string $transitTime
     * @return string
     */
    public function getArrival(string $transitTime): string
    {
        if ($transitTime > 0) {
            // Pure hack until its confirmed
            if ($transitTime >= 21) {
                return '21 days or more';
            } elseif ($transitTime >= 14) {
                return '14-21 days';
            } elseif ($transitTime >= 7) {
                return '7-14 days';
            } elseif ($transitTime >= 3) {
                return '3-7 days';
            } elseif ($transitTime >= 1) {
                return '1-3 days';
            }
        }

        return '';
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
     * Gets the flat rate carrier name from the plugin settings.
     *
     * @return string
     */
    public function getFlatRateHandle(): string
    {
        return $this->_getSetting('flatRateCarrierName');
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

        $packageDetailsList = $this->getPackageDetailsList();

        if (!$packageDetailsList || !$packageDetailsList[0]) {
            $this->_logMessage(__METHOD__, 'Package Details List was null');

            return false;
        }

        $packingName = 'SingleBoxOrPallet';

        if (!empty($packageDetailsList[0]['Name'])) {
            $packingName = $packageDetailsList[0]['Name'];
        } elseif (!empty($packageDetailsList[0]['PackageNumber'])) {
            $packingName = $packageDetailsList[0]['PackageNumber'];
        }

        $packingWeight = $packageDetailsList[0]['Weight'];

        // Cart ID + Packing Method + Total Weight
        $cacheKey = "$order->id--$packingName--$packingWeight";

        return str_replace(' ', '--', $cacheKey);
    }

    /**
     * Makes a request to our SalesForce API to get rates. Returns an array of rates.
     *
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return mixed|null
     * @throws GuzzleException
     */
    private function _getRequest(
        string $method,
        string $uri,
        array $options = [],
    ) {
        $this->_logMessage(__METHOD__, 'Making new API request :: ' . $uri);

        $response = $this->_getClient()->request(
            $method,
            ltrim($uri, '/'),
            $options,
        );

        return Json::decode((string) $response->getBody());
    }

    /**
     * Creates a Guzzle client with our settings and returns it.
     *
     * @return Client
     */
    private function _getClient(): Client
    {
        if ($this->_client) {
            return $this->_client;
        }

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => $this->_getSetting('apiUrl'),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'FlatworldLocation' => $this->_getSetting('username'),
                'FlatworldLicenseKey' => $this->_getSetting('licenseKey'),
            ],
        ]);
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