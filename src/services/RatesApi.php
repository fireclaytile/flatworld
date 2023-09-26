<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\base\Component;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\services\salesforce\SalesforceRestConnection;
use fireclaytile\flatworld\services\salesforce\models\ShippingRequest;
use fireclaytile\flatworld\services\salesforce\models\LineItem;
use verbb\postie\Postie;

// Report all errors
error_reporting(E_ALL);

/**
 * Service class for working with the Flatworld Rates API.
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services
 */
class RatesApi extends Component
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
     * @var bool
     */
    private bool $_salesforceEnabled;

    /**
     * Instance of the SalesforceRestConnection class.
     *
     * @var mixed
     */
    public $sf;

    /**
     * RatesApi constructor.
     */
    function __construct()
    {
        $this->_flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');
        $this->_salesforceEnabled = $this->_flatworld->getSetting(
            'enableSalesforceApi',
        );
    }

    /**
     * Get rates from Salesforce.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @param mixed $sf SalesforceRestConnection object - useful for tests
     * @return string
     */
    public function getRates(
        ShippingRequest $shippingRequest,
        $sf = null,
    ): string {
        if (!$this->_salesforceEnabled || $shippingRequest == null) {
            return '';
        }

        if ($sf == null) {
            $this->salesforceConnect();
        } else {
            $this->sf = $sf;
        }

        $rates = $this->sf->getRates($shippingRequest);

        return json_encode($rates);
    }

    /**
     * Create a connection to Salesforce. Returns true if successful, false if not.
     *
     * @return bool
     */
    public function salesforceConnect(): bool
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
