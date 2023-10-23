<?php

namespace OData\Client;

use ArrayAccess;
use Exception;
use GuzzleHttp\Client;

class OdataConnection implements ArrayAccess
{
    /**
     * @var array
     */
    private array $container = [];

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var string
     */
    private string $url;

    /**
     * @var array
     */
    private array $options;

    /**
     * @param string $url
     * @param array|null $options
     */
    public function __construct(string $url, ?array $options = [])
    {
        $this->client = new Client();

        if (!empty($url) && !str_ends_with($url, '/')) {
            $url .= '/';
        }
        $this->url = $url;
        $this->options = array_merge_recursive(
            $options,
            [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 300
            ]
        );
    }

    /**
     * Параметры авторизации интерфейса OData
     *
     * @param string $username
     * @param string $password
     */
    public function setAuth(string $username, string $password)
    {
        $this->options = array_merge_recursive(
            $this->options,
            [
                'auth' => [
                    $username,
                    $password
                ]
            ]
        );
    }

    /**
     * Параметры использования прокси сервиса
     *
     * @param string $proxyHost
     * @param string $proxyPort
     * @param bool|null $isSecured
     */
    public function setProxy(string $proxyHost, string $proxyPort, ?bool $isSecured = false)
    {
        $this->options = array_merge_recursive(
            $this->options,
            [
                'proxy' => sprintf(
                    ($isSecured ? 'https' : 'http') . '://%s:%s',
                    $proxyHost,
                    $proxyPort
                )
            ]
        );
    }

    /**
     * Переопределение таймаута интерфейса OData
     *
     * @param int $timeout
     */
    public function setTimeout(int $timeout)
    {
        $this->options = array_merge_recursive(
            $this->options,
            [
                'timeout' => $timeout
            ]
        );
    }

    public function __get($name)
    {
        if (in_array($name, ['client', 'url', 'options'])) {
            return $this->{$name};
        } elseif (!isset($this->container[$name])) {
            $this->container[$name] = new OdataContainer($this, $name);
        }

        return $this->container[$name];
    }

    /**
     * @param $offset
     * @param $value
     * @throws Exception
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * @param $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->container[$offset] ?? null;
    }
}