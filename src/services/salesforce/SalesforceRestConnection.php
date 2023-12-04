<?php

namespace fireclaytile\flatworld\services\salesforce;

use Exception;
use fireclaytile\flatworld\models\RestDetails;
use fireclaytile\flatworld\models\ShippingRequest;
use fireclaytile\flatworld\services\RatesRestConnectionInterface;

/**
 * SalesforceRestConnection Class
 *
 * Makes REST API calls to Salesforce.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 */
class SalesforceRestConnection implements RatesRestConnectionInterface
{
    /**
     * Salesforce access token.
     *
     * @var string
     */
    private string $accessToken;

    /**
     * Salesforce instance URL.
     *
     * @var string
     */
    private string $instanceURL;

    /**
     * Salesforce base endpoint.
     *
     * @var string
     */
    private string $baseEndpoint = '/services/apexrest/';

    /**
     * Salesforce API client ID.
     *
     * @var string
     */
    private $clientId;

    /**
     * Salesforce API client secret.
     *
     * @var string
     */
    private $clientSecret;

    /**
     * Salesforce API username.
     *
     * @var string
     */
    private $username;

    /**
     * Salesforce API password.
     *
     * @var string
     */
    private $password;

    /**
     * Salesforce API login URL.
     *
     * @var string
     */
    private $loginURL;

    /**
     * Initializes a new SalesforceRestConnection object.
     *
     * @param string $clientId Salesforce app's client ID.
     * @param string $clientSecret Salesforce app's client secret.
     * @param string $username Salesforce account username.
     * @param string $password Salesforce account password.
     * @param string $loginURL Salesforce login endpoint URL. Defaults to 'https://login.salesforce.com/services/oauth2/token'.
     */
    public function __construct(
        $clientId,
        $clientSecret,
        $username,
        $password,
        $loginURL = 'https://login.salesforce.com/services/oauth2/token',
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->username = $username;
        $this->password = $password;
        $this->loginURL = $loginURL;
    }

    /**
     * Establishes a connection to Salesforce using the REST API.
     *
     * This method sends a POST request to the Salesforce login URL with the client ID, client secret,
     * username, and password. If the request is successful, it sets the access token and instance URL
     * for the Salesforce connection.
     *
     * @throws Exception If there is an error with the cURL request, if the HTTP status code of the
     * response is not 200, or if the response data does not include an access token and instance URL.
     */
    public function connect(): void
    {
        $params = http_build_query([
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $curl = curl_init($this->loginURL);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

        $json_response = curl_exec($curl);

        if ($json_response === false) {
            throw new Exception('Failed to execute cURL: ' . curl_error($curl));
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            throw new Exception(
                $this->constructCurlErrorMessage(
                    $this->loginURL,
                    $status,
                    $json_response,
                    $curl,
                ),
            );
        }

        curl_close($curl);

        $data = json_decode($json_response, true);

        if (!isset($data['access_token'], $data['instance_url'])) {
            throw new Exception('Invalid response from Salesforce');
        }

        $this->accessToken = $data['access_token'];
        $this->instanceURL = $data['instance_url'];
    }

    /**
     * Gets shipping rates from Salesforce.
     *
     * @param ShippingRequest $shippingRequest Shipping request object
     * @return array
     * @throws Exception
     */
    public function getRates(ShippingRequest $shippingRequest): array
    {
        $url = $this->baseEndpoint . 'ShippingQuote';
        $restDetails = new RestDetails(
            'POST',
            $url,
            json_encode($shippingRequest),
        );

        $response = $this->makeRequest(
            $this->accessToken,
            $this->instanceURL,
            $restDetails,
        );

        if (!is_array($response)) {
            throw new Exception('Invalid response from Salesforce');
        }

        return $response;
    }

    /**
     * Makes a REST API request to Salesforce.
     *
     * @param string $accessToken Salesforce access token
     * @param string $instanceURL Salesforce instance URL
     * @param RestDetails $restDetails REST details object
     * @return array
     * @throws Exception
     */
    private function makeRequest(
        string $accessToken,
        string $instanceURL,
        RestDetails $restDetails,
    ): array {
        $curl = curl_init($instanceURL . $restDetails->url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $header = ["Authorization: OAuth $accessToken"];
        $successStatus = match ($restDetails->method) {
            'POST' => $this->handlePost($curl, $restDetails, $header),
            'GET' => 200,
            default => throw new Exception(
                'Invalid REST API method: ' . $restDetails->method,
            ),
        };

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        $json_response = curl_exec($curl);

        if ($json_response === false) {
            throw new Exception('Failed to execute cURL: ' . curl_error($curl));
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != $successStatus) {
            throw new Exception(
                $this->constructCurlErrorMessage(
                    $restDetails->url,
                    $status,
                    $json_response,
                    $curl,
                ),
            );
        }

        curl_close($curl);

        $response = json_decode($json_response, true);

        if (!is_array($response)) {
            throw new Exception('Invalid response from Salesforce');
        }

        return $response;
    }

    /**
     * Handles a POST request.
     *
     * @param resource $curl The cURL handle
     * @param RestDetails $restDetails REST details object
     * @param array $header The request headers
     * @return int The success status code
     * @throws Exception
     */
    private function handlePost(
        $curl,
        RestDetails $restDetails,
        array &$header,
    ): int {
        if ($restDetails->body === null) {
            throw new Exception('Invalid POST without body');
        }

        $header[] = 'Content-type: application/json';

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $restDetails->body);

        return 200;
    }

    /**
     * Constructs an error message for a failed cURL request.
     *
     * @param string $url The URL of the request
     * @param int $status The HTTP status code
     * @param string $response The response from the server
     * @param mixed $curl The cURL handle
     * @return string The error message
     */
    private function constructCurlErrorMessage(
        string $url,
        int $status,
        string $response,
        mixed $curl,
    ): string {
        return sprintf(
            'Call to URL %s failed with status %d, response: %s, curl_error: %s, curl_errno: %d',
            $url,
            $status,
            $response,
            curl_error($curl),
            curl_errno($curl),
        );
    }
}
