<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\helpers\Json;
use fireclaytile\flatworld\Flatworld as FlatworldPlugin;
use fireclaytile\flatworld\models\OrderMetaData;
use fireclaytile\flatworld\models\ShippingRate;
use fireclaytile\flatworld\models\ShippingRequest;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use GuzzleHttp\Exception\GuzzleException;
use verbb\postie\helpers\PostieHelper;
use verbb\postie\Postie;

/**
 * Service class Rates.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
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
    private ShippingRequest $shippingRequest;

    /**
     * @var boolean|null
     */
    private bool|null $_loggingEnabled;

    /**
     * Rates constructor.
     */
    public function __construct($loggingEnabled = false, $settings = null)
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
     * Retrieves shipping rates for an order.
     *
     * First checks the rates cache. If rates are not in the cache, it makes an API request to retrieve them.
     * The retrieved rates are then cached for future use.
     *
     * @param Order $order The order to get rates for.
     * @param OrderMetaData $orderMetaData The metadata of the order.
     * @return array Returns an array of rates for the order.
     */
    public function getRates(
        Order $order,
        OrderMetaData $orderMetaData,
        ShippingRequest $shippingRequest,
    ): array {
        $this->setOrder($order);
        $this->shippingRequest = $shippingRequest;

        // Lets check the rates cache before making an API request - this will be an array or be false
        $ratesCache = $this->getRatesCache();

        if (!empty($ratesCache) && is_array($ratesCache)) {
            return $this->_modifyRatesEvent($ratesCache, $this->_order);
        }

        $this->requestRates();

        $rates = $this->responseRates($orderMetaData);

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
    public function setOrder(Order $order): void
    {
        $this->_order = clone $order;
    }

    /**
     * Gets the _order property.
     *
     * @return mixed
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * Gets the shippingRequest property.
     *
     * @return ShippingRequest
     */
    public function getShippingRequest(): ShippingRequest
    {
        return $this->shippingRequest;
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
        $result = $this->_ratesApiService->testRatesConnection();

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
        $shippingRequest = $this->shippingRequest;
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
    public function responseRates(OrderMetaData $orderMetaData): array
    {
        $response = $this->getResponse();
        $allowedCarrierServices = $this->_getSetting('carrierClassOfServices');
        $shippingRates = new ShippingRates($response, $allowedCarrierServices);

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
            'Cheapest rate: ' . Json::encode($cheapestRate),
        );
        $this->_logMessage(
            __METHOD__,
            'Fastest rate: ' . Json::encode($quickestRate),
        );

        $cheapestServiceHandle = $cheapestRate
            ? $cheapestRate->getServiceHandle()
            : '';
        $quickestServiceHandle = $quickestRate
            ? $quickestRate->getServiceHandle()
            : '';

        $rates = [];

        $this->_setCheapestAndQuickestRates(
            $rates,
            $cheapestServiceHandle,
            $cheapestRate,
            $quickestServiceHandle,
            $quickestRate,
        );

        // Sort so the lowest cost carrier is first/default
        $amount = array_column($rates, 'amount');
        array_multisort($amount, SORT_ASC, $rates);

        // Shipping for samples only orders will differ from standard orders
        // The rates and carrienr handles are set in the plugin settings
        // (We set this on the lowest cost carrier which also overrides flat rate cost)
        $this->_setSampleOnlyShippingRates($rates, $orderMetaData);

        return $this->_modifyRatesEvent($rates, $this->_order);
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
     * Gets the Trade customer flat rate amount from the plugin settings.
     *
     * @return float
     */
    public function getTradeFlatRateAmount(): float
    {
        $flatRateAmount = number_format(
            $this->_getSetting('tradeFlatRateCarrierCost'),
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
     * Sets the shipping rates for the cheapest and quickest options.
     *
     * @param array $rates The shipping rates array.
     * @param string $cheapestServiceHandle The handle of the cheapest shipping service.
     * @param ShippingRate $cheapestRate The cheapest shipping rate.
     * @param string $quickestServiceHandle The handle of the quickest shipping service.
     * @param ShippingRate $quickestRate The quickest shipping rate.
     */
    private function _setCheapestAndQuickestRates(
        array &$rates,
        string $cheapestServiceHandle,
        ShippingRate $cheapestRate,
        string $quickestServiceHandle,
        ShippingRate $quickestRate,
    ): void {
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
                $rates[$handle]['type'] = $quickestRate->type;
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
                $rates[$handle]['type'] = $quickestRate->type;
            }
        }

        if (!$foundServiceHandle) {
            $this->_logMessage(
                __METHOD__,
                'Didnt find any matching carrier handles',
            );
        }
    }

    /**
     * Gets the shipping rate amount for samples-only orders.
     *
     * @return float The shipping rate amount.
     */
    private function _getSamplesOnlyShippingRateAmount(): float
    {
        if (
            !empty($this->_order->customer) &&
            $this->_order->customer->isInGroup('customersTrade15')
        ) {
            $amount = $this->getTradeFlatRateAmount();

            $this->_logMessage(
                __METHOD__,
                'Applied Free Shipping on Samples only for Trade account',
            );
        } else {
            $amount = $this->getFlatRateAmount();

            $this->_logMessage(
                __METHOD__,
                'Applied Flat Rate Shipping on Samples only for samples-only orders',
            );
        }

        return $amount;
    }

    /**
     * Sets the shipping rates for sample-only orders.
     *
     * @param array $rates The shipping rates array.
     */
    private function _setSampleOnlyShippingRates(
        array &$rates,
        OrderMetaData $orderMetaData,
    ): void {
        if (
            !$orderMetaData->containsStandardProducts &&
            !$orderMetaData->containsMerchandise &&
            $orderMetaData->containsSampleProducts
        ) {
            $firstCarrier = array_slice($rates, 0, 1);
            $firstServiceHandle = key($firstCarrier);

            $rates[$firstServiceHandle][
                'amount'
            ] = $this->_getSamplesOnlyShippingRateAmount();

            $flatRateHandle = $this->getFlatRateHandle();
            if (!empty($flatRateHandle)) {
                $rates = $this->_changeKey(
                    $rates,
                    $firstServiceHandle,
                    $flatRateHandle,
                );
            }
        }
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

        return Logger::logMessage($msg, true);
    }

    /**
     * Replaces a key in an array with a new key.
     *
     * @param array $array
     * @param int|string $oldKey
     * @param int|string $newKey
     * @return array
     */
    private function _changeKey(
        array $array,
        int|string $oldKey,
        int|string $newKey,
    ): array {
        if (!array_key_exists($oldKey, $array)) {
            return $array;
        }

        $keys = array_keys($array);
        $keys[array_search($oldKey, $keys)] = $newKey;

        return array_combine($keys, $array);
    }
}
