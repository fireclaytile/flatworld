<?php

namespace fireclaytile\flatworld\services\salesforce;

use fireclaytile\flatworld\services\salesforce\models\RestDetails;
use fireclaytile\flatworld\services\salesforce\models\ShippingRequest;

/**
 * Class for making REST API calls to Salesforce.
 */
class SalesforceRestConnection
{
    private $_accessToken;
    private $_instanceURL;
    private $_baseEndpoint = '/services/apexrest/';

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

    public function __destruct()
    {
    }

    /**
     * Gets shipping rates from Salesforce.
     *
     * @param ShippingRequest $shippingRequest
     * @return void
     */
    public function getRates(ShippingRequest $shippingRequest)
    {
        $url = $this->_baseEndpoint . 'ShippingQuote';
        $restDetails = new RestDetails('GET', $url);

        return $this->_makeRequest(
            $this->_accessToken,
            $this->_instanceURL,
            $restDetails,
        );
    }

    private function _makeRequest(
        string $accessToken,
        string $instanceURL,
        RestDetails $restDetails,
    ) {
        $curl = curl_init($instanceURL . $restDetails->url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $header = ["Authorization: OAuth $accessToken"];
        $successStatus;

        switch ($restDetails->method) {
            case 'POST':
                if ($restDetails->body == null) {
                    throw new Exception(
                        'SalesforceRestConnection->makeRequest: Invalid POST without body',
                    );
                }

                array_push($header, 'Content-type: application/json');
                $successStatus = 201;

                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt(
                    $curl,
                    CURLOPT_POSTFIELDS,
                    json_encode($restDetails->body),
                );
                break;

            case 'GET':
                $successStatus = 200;
                break;

            default:
                throw new Exception(
                    'SalesforceRestConnection->makeRequest: Invalid REST API method-> ' .
                        $restDetails->method,
                );
                break;
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
