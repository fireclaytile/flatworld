<?php

namespace fireclaytile\flatworld\services\salesforce;

use Exception;
use fireclaytile\flatworld\services\salesforce\models\RestDetails;
use fireclaytile\flatworld\services\salesforce\models\ShippingRequest;

/**
 * SalesforceRestConnection Class
 *
 * Makes REST API calls to Salesforce.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 */
class SalesforceRestConnection
{
    /**
     * Salesforce access token.
     *
     * @var string
     */
    private string $_accessToken;

    /**
     * Salesforce instance URL.
     *
     * @var string
     */
    private string $_instanceURL;

    /**
     * Salesforce base endpoint.
     *
     * @var string
     */
    private string $_baseEndpoint = '/services/apexrest/';

    /**
     * SalesforceRestConnection constructor.
     *
     * Makes a connection to Salesforce and stores the access token and instance URL.
     *
     * @param string $clientId Salesforce API client ID
     * @param string $clientSecret Salesforce API client secret
     * @param string $username Salesforce API username
     * @param string $password Salesforce API password
     * @param string $loginURL Salesforce API login URL
     */
    public function __construct(
        $clientId,
        $clientSecret,
        $username,
        $password,
        $loginURL = 'https://login.salesforce.com/services/oauth2/token',
    ) {
        $params =
            'grant_type=password' .
            '&client_id=' .
            $clientId .
            '&client_secret=' .
            $clientSecret .
            '&username=' .
            $username .
            '&password=' .
            $password;

        $curl = curl_init($loginURL);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

        $json_response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            die(
                "Error: call to URL $loginURL failed with status $status, response $json_response, curl_error " .
                    curl_error($curl) .
                    ', curl_errno ' .
                    curl_errno($curl)
            );
        }

        curl_close($curl);

        $data = json_decode($json_response, true);

        $this->_accessToken = $data['access_token'];
        $this->_instanceURL = $data['instance_url'];
    }

    /**
     * SalesforceRestConnection destructor.
     */
    public function __destruct()
    {
    }

    /**
     * Gets shipping rates from Salesforce.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @return mixed
     */
    public function getRates(ShippingRequest $shippingRequest): mixed
    {
        $url = $this->_baseEndpoint . 'ShippingQuote';
        $restDetails = new RestDetails('POST', $url);

        $restDetails->body = json_encode($shippingRequest);

        return $this->_makeRequest(
            $this->_accessToken,
            $this->_instanceURL,
            $restDetails,
        );
    }

    /**
     * Makes a REST API request to Salesforce.
     *
     * @param string $accessToken Salesforce access token
     * @param string $instanceURL Salesforce instance URL
     * @param RestDetails $restDetails REST details object
     * @return mixed
     */
    private function _makeRequest(
        string $accessToken,
        string $instanceURL,
        RestDetails $restDetails,
    ): mixed {
        $curl = curl_init($instanceURL . $restDetails->url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $header = ["Authorization: OAuth $accessToken"];
        $successStatus = 0;

        switch ($restDetails->method) {
            case 'POST':
                if ($restDetails->body == null) {
                    throw new Exception(
                        'SalesforceRestConnection->makeRequest: Invalid POST without body',
                    );
                }

                array_push($header, 'Content-type: application/json');
                $successStatus = 200;

                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $restDetails->body);
                break;

            case 'GET':
                $successStatus = 200;
                break;

            default:
                throw new Exception(
                    'SalesforceRestConnection->makeRequest: Invalid REST API method-> ' .
                        $restDetails->method,
                );
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        $json_response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != $successStatus) {
            throw new Exception(
                "SalesforceRestConnection->makeRequest: Call to URL $restDetails->url failed with status $status, response $json_response, curl_error " .
                    curl_error($curl) .
                    ', curl_errno ' .
                    curl_errno($curl) .
                    "\n" .
                    $json_response,
            );
        }

        curl_close($curl);

        $response = json_decode($json_response, true);

        return $response;
    }
}
