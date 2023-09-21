<?php

namespace fireclaytile\flatworld\services\salesforce\models;

/**
 * RestDetails model class.
 *
 * Used to store details about a REST API call.
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services\salesforce\models
 */
class RestDetails
{
    /**
     * Request HTTP method.
     *
     * @var string
     */
    public string $method;

    /**
     * Request URL.
     *
     * @var string
     */
    public string $url;

    /**
     * Reference ID.
     *
     * @var string|null
     */
    public ?string $referenceId;

    /**
     * Request body.
     *
     * @var string|null
     */
    public ?string $body;

    /**
     * RestDetails constructor.
     *
     * @param string $method HTTP method
     * @param string $url URL
     * @param string|null $referenceId Reference ID
     * @param string|null $body Request body
     */
    public function __construct(
        string $method,
        string $url,
        ?string $referenceId = null,
        ?string $body = null,
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->body = $body;
        $this->referenceId = $referenceId;
    }
}
