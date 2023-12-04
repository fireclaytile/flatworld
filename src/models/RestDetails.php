<?php

namespace fireclaytile\flatworld\models;

/**
 * RestDetails model class.
 *
 * Used to store details about a REST API call.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
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
     * @param string|null $body Request body
     */
    public function __construct(
        string $method,
        string $url,
        ?string $body = null,
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->body = $body;
    }
}
