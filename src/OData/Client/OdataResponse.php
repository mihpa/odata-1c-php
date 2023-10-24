<?php

namespace OData\Client;

use Psr\Http\Message\ResponseInterface;

class OdataResponse
{
    /**
     * @var ResponseInterface|null
     */
    private ?ResponseInterface $response;

    /**
     * @var array|null
     */
    private ?array $array;

    public function __construct() {
    }

    public function make(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function toArray(): array|null
    {
        if (empty($this->array)) {
            $this->array = json_decode($this->response->getBody(), true);
        }
        return $this->array;
    }

    public function getResponseCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getResponsePhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function getOdataErrorCode(): int|null
    {
        $body = $this->toArray();

        if (isset($body['odata.error']['code'])) {
            return $body['odata.error']['code'];
        }

        return null;
    }

    public function getOdataErrorPhrase(): string|null
    {
        $body = $this->toArray();

        if (isset($body['odata.error']['message']['value'])) {
            return $body['odata.error']['message']['value'];
        }

        return null;
    }

    public function getLastId(): string|null
    {
        if ($this->response->hasHeader('Location')) {
            preg_match('/guid\'(.*?)\'/', implode(' ', $this->response->getHeader('Location')), $matches);
            if ($matches) {
                return $matches[1];
            }
        }

        return null;
    }

    public function values(): array
    {
        $body = $this->toArray();

        if (isset($body['value'])) {
            return $body['value'];
        }

        if (isset($body['Ref_Key'])) {
            return [$body];
        }

        return $body;
    }
}