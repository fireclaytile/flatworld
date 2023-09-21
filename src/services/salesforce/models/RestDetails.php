<?php

namespace fireclaytile\flatworld\services\salesforce\models;

/**
 * Helper model for holding REST request details.
 */
class RestDetails
{
    public string $method;
    public string $url;
    public string|null $referenceId;
    public string|null $body;

    public function __construct(
        string $method,
        string $url,
        string|null $referenceId = null,
        string|null $body = null,
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->body = $body;
        $this->referenceId = $referenceId;
    }
}
