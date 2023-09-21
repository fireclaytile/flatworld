<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\services;

use Craft;
use craft\base\Component;
use fireclaytile\flatworld\providers\Flatworld;
use fireclaytile\flatworld\services\salesforce\SalesforceRestConnection;
use fireclaytile\flatworld\services\salesforce\models\ShippingRequest;
use fireclaytile\flatworld\services\salesforce\models\LineItem;
use verbb\postie\Postie;

// Report all errors
error_reporting(E_ALL);

// require_once('salesforce/SalesforceRestConnection.php');

/**
 * Class RatesApi
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services
 */
class RatesApi extends Component
{
    /**
     * Instance of the Flatworld Postie Provider class.
     *
     * @var Flatworld
     */
    protected Flatworld $flatworld;

    /**
     * Instance of the SalesforceRestConnection class.
     *
     * @var mixed
     */
    public $sf;

    /**
     * Salesforce enabled?
     *
     * @var bool
     */
    private bool $salesforceEnabled;

    function __construct()
    {
        $this->flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');
        $this->salesforceEnabled = !$this->flatworld->getSetting(
            'enableSalesforceApi',
        );
    }

    /**
     * Get rates from Salesforce.
     *
     * @param ShippingRequest $shippingRequest
     * @param mixed $sf
     * @return string
     */
    public function getRates(
        ShippingRequest $shippingRequest,
        $sf = null,
    ): string {
        if (!$this->salesforceEnabled || $shippingRequest == null) {
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
