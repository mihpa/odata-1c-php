<?php

namespace OData\Client;

use ArrayAccess;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use OData\Client\Exception\GuidValidationException;
use OData\Client\Exception\OdataResponse;

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
    protected string $url;

    /**
     * @var array
     */
    private array $options;

    /**
     * @var array
     */
    private array $querySelect = [];

    /**
     * @var array
     */
    private array $queryExpand = [];

    /**
     * @var array
     */
    private array $queryFilter = [];

    /**
     * @var array
     */
    private array $queryOrder = [];

    /**
     * @var bool
     */
    private bool $queryMetadata = false;

    /**
     * Код ответа интерфейса OData
     *
     * @var int
     */
    public int $responseCode;

    /**
     * Детализация ответа интерфейса OData
     *
     * @var string
     */
    public string $responseReason;

    /**
     * Ответа интерфейса OData
     *
     * @var OdataResponse|null
     */
    private ?OdataResponse $response;

    /**
     * @param string $url
     * @param array|null $options
     */
    public function __construct(string $url, ?array $options = [])
    {
        $this->client = new Client();

        $this->url = $url;
        $this->options = array_replace_recursive(
            [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 300
            ],
            $options
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
        $this->options = array_replace_recursive(
            [
                'auth' => [
                    $username,
                    $password
                ]
            ],
            $this->options
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
        $this->options = array_replace_recursive(
            [
                'proxy' => sprintf(
                    ($isSecured ? 'https' : 'http') . '://%s:%s',
                    $proxyHost,
                    $proxyPort
                )
            ],
            $this->options
        );
    }

    /**
     * Переопределение таймаута интерфейса OData
     *
     * @param int $timeout
     */
    public function setTimeout(int $timeout)
    {
        $this->options = array_replace_recursive(
            [
                'timeout' => $timeout
            ],
            $this->options
        );
    }

    /**
     * Признак запроса метаданных
     */
    public function metadata()
    {
        $this->queryMetadata = true;
    }

    /**
     * Перечень свойств сущности
     *
     * @param array $data
     * @return OdataConnection
     */
    public function select(array $data)
    {
        $this->querySelect = array_replace_recursive($data, $this->querySelect);
        return $this;
    }

    /**
     * Данные связанных сущностей
     *
     * @param array $data
     * @return OdataConnection
     */
    public function expand(array $data)
    {
        $this->queryExpand = array_replace_recursive($data, $this->queryExpand);
        return $this;
    }

    /**
     * Фильтр сущностей
     *
     * @param array $data
     * @return OdataConnection
     */
    public function filter(array $data)
    {
        $this->queryFilter = array_replace_recursive($data, $this->queryFilter);
        return $this;
    }

    /**
     * Сортировка сущностей
     *
     * @param array $data
     * @return OdataConnection
     */
    public function order(array $data)
    {
        $this->queryOrder = array_replace_recursive($data, $this->queryOrder);
        return $this;
    }

    /**
     * @param int $quantity
     * @return OdataConnection
     */
    public function top(int $quantity)
    {
        $this->options['query']['$top'] = $quantity;
        return $this;
    }

    /**
     * Получение свойств сущности
     *
     * @param string $object
     * @param string|null $guid
     * @return array|null
     * @throws GuidValidationException
     */
    public function get(string $object, string $guid = null)
    {
        if (!Guid::is_valid($guid)) {
            throw new GuidValidationException();
        }

        $request = $object;
        if ($guid) {
            $request .= sprintf('(guid\'%s\')', $guid);
        }

        if ($this->request('GET', $request)) {
            return $this->response->values();
        }

        return null;
    }

    /**
     * Создание сущности
     *
     * @param string $object
     * @param array $data
     * @return bool
     * @throws GuidValidationException
     */
    public function create(string $object, array $data): bool
    {
        return $this->update($object, null, $data);
    }

    /**
     * Изменение сущности
     *
     * @param string $object
     * @param string $guid
     * @param array $data
     * @return bool
     * @throws GuidValidationException
     */
    public function update(string $object, string $guid, array $data): bool
    {
        if (!Guid::is_valid($guid)) {
            throw new GuidValidationException();
        }

        $method = $guid ? 'PATCH' : 'POST';

        $request = $object;
        if ($guid) {
            $request .= sprintf('(guid\'%s\')', $guid);
        }

        return $this->request($method, $request, ['json' => $data]);
    }

    /**
     * Удаление сущности
     *
     * @param string $object
     * @param string $guid
     * @return bool
     * @throws GuidValidationException
     */
    public function delete(string $object, string $guid): bool
    {
        if (!Guid::is_valid($guid)) {
            throw new GuidValidationException();
        }

        return $this->request('DELETE', sprintf('/%s(guid\'%s\')', $object, $guid));
    }

    /**
     * @param string $method
     * @param string $request
     * @param array|null $options
     * @return bool
     */
    private function request(string $method, string $request, ?array $options = []): bool
    {
        if (!empty($options)) {
            $options = array_replace_recursive($options, $this->options);
        }

        $format = 'application/json';
        if (!$this->queryMetadata) {
            $format .= ';odata=nometadata';
            $options['query']['$format'] = $format;
        }

        $result = true;
        try {
            $this->response = new OdataResponse(
                $this->client->request($method, $request, $options)
            );
        } catch (ClientException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Код ответа интерфейса OData
     *
     * @return int
     */
    public function getResponseCode(): int
    {
        return $this->response?->getResponseCode();
    }

    /**
     * Детализация ответа интерфейса OData
     *
     * @return string
     */
    public function getResponsePhrase(): string
    {
        return $this->response?->getResponsePhrase();
    }

    /**
     * Код ответа OData (1С)
     *
     * @return int|null
     */
    public function getOdataErrorCode(): int|null
    {
        return $this->response?->getOdataErrorCode();
    }

    /**
     * Детализация ответа OData (1С)
     *
     * @return string|null
     */
    public function getOdataErrorPhrase(): string|null
    {
        return $this->response?->getOdataErrorPhrase();
    }

    /**
     * @param $offset
     * @param $value
     * @throws Exception
     */
    public function offsetSet($offset, $value): void
    {
        throw new Exception('Нельзя изменить значение, доступно только чтение');
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return !empty($this->response?->toArray()[$offset] ?? []);
    }

    /**
     * @param $offset
     * @return void
     */
    public function offsetUnset($offset): void
    { }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->response?->toArray()[$offset] ?? null;
    }
}