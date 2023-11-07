<?php

namespace fireclaytile\flatworld\services;

use craft\base\Component;
use Exception;
use fireclaytile\flatworld\models\ShippingRequest;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\services\salesforce\SalesforceRestConnection;
use verbb\postie\Postie;

// Report all errors
error_reporting(E_ALL);

/**
 * Service class for working with the Flatworld Rates API.
 *
 * @author      Fireclay Tile
 * @since       0.8.0
 */
class RatesApi extends Component implements RatesApiInterface
{
    /**
     * Instance of the Flatworld Postie Provider class.
     *
     * @var FlatworldProvider
     */
    private FlatworldProvider $_flatworld;

    /**
     * Salesforce enabled?
     *
     * @var bool|null
     */
    private bool|null $_salesforceEnabled;

    /**
     * Instance of the SalesforceRestConnection class.
     *
     * @var mixed
     */
    private $_sf;

    /**
     * RatesApi constructor.
     */
    public function __construct($settings = null)
    {
        $this->_flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');

        if ($settings) {
            $this->_flatworld->settings = $settings;
        }

        $this->_salesforceEnabled = $this->_flatworld->getSetting(
            'enableSalesforceApi',
        );
    }

    /**
     * Get rates from Salesforce.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @return string
     */
    public function getRates(ShippingRequest $shippingRequest): string
    {
        if (!$this->_salesforceEnabled || $shippingRequest == null) {
            return '';
        }

        $this->_salesforceConnect();
        $rates = $this->_sf->getRates($shippingRequest);

        return json_encode($rates);
    }

    /**
     * Tests the connection to the Rates API.
     *
     * @return boolean
     */
    public function testRatesConnection(): bool
    {
        return $this->_salesforceConnect();
    }

    /**
     * Create a connection to Salesforce. Returns true if successful, false if not.
     *
     * @return bool
     */
    private function _salesforceConnect(): bool
    {
        try {
            // get params from Postie settings
            $apiConsumerKey = $this->_flatworld->getSetting('apiConsumerKey');
            $apiConsumerSecret = $this->_flatworld->getSetting(
                'apiConsumerSecret',
            );
            $apiUsername = $this->_flatworld->getSetting('apiUsername');
            $apiPassword = $this->_flatworld->getSetting('apiPassword');
            $apiUrl = $this->_flatworld->getSetting('apiUrl');

            $this->_sf = new SalesforceRestConnection(
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
