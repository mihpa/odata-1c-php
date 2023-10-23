<?php

namespace OData\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use OData\Client\Exception\CorruptObjectNameException;
use OData\Client\Exception\GuidValidationException;

class OdataContainer
{
    /**
     * @var OdataConnection
     */
    private OdataConnection $connection;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var array
     */
    protected array $querySelect = [];

    /**
     * @var array
     */
    protected array $queryExpand = [];

    /**
     * @var array
     */
    protected array $queryFilter = [];

    /**
     * @var string
     */
    protected string $queryOrderBy;

    /**
     * @var bool
     */
    private bool $queryMetadata = false;

    /**
     * @var int
     */
    private int $top;

    /**
     * Ответ интерфейса OData
     *
     * @var OdataResponse|null
     */
    private ?OdataResponse $response;

    /**
     * @param OdataConnection $connection
     * @param string $name
     * @throws CorruptObjectNameException
     */
    public function __construct(OdataConnection $connection, string $name)
    {
        $this->connection = $connection;

        $nameArray = explode('/', $name, 2);

        if (count($nameArray) != 2) {
            throw new CorruptObjectNameException();
        }

        $isObjectNameCorrect = false;
        foreach ($this->objects() as $key => $value) {
            if ($nameArray[0] == $key) {
                $nameArray[0] = $value;
                $isObjectNameCorrect = true;
            }
        }
        if (!$isObjectNameCorrect) {
            throw new CorruptObjectNameException();
        }

        $this->name = implode('_', $nameArray);
    }

    private function objects() {
        return [
            'Справочник'                => 'Catalog',
            'Документ'                  => 'Document',
            'Журнал документов'         => 'DocumentJournal',
            'Константа'                 => 'Constant',
            'План обмена'               => 'ExchangePlan',
            'План счетов'               => 'ChartOfAccounts',
            'План видов расчета'        => 'ChartOfCalculationTypes',
            'План видов характеристик'  => 'ChartOfCharacteristicTypes',
            'Регистр сведений'          => 'InformationRegisters',
            'Регистр накопления'        => 'AccumulationRegister',
            'Регистр расчета'           => 'CalculationRegister',
            'Регистр бухгалтерии'       => 'AccountingRegister',
            'Бизнес-процесс'            => 'BusinessProcess',
            'Задача'                    => 'Task',
            'Перечисления'              => 'Enum',
        ];
    }

    /**
     * Перечень свойств сущности
     *
     * @param string|array $data
     * @return OdataContainer
     */
    public function select(string|array $data)
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        $this->querySelect = array_merge_recursive($this->querySelect, $data);
        return $this;
    }

    /**
     * Данные связанных сущностей
     *
     * @param string|array $data
     * @return OdataContainer
     */
    public function expand(string|array $data)
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        $this->queryExpand = array_merge_recursive($this->queryExpand, $data);
        return $this;
    }

    /**
     * Фильтр сущностей
     *
     * @param string|array $data
     * @return OdataContainer
     */
    public function filter(string|array $data)
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        $this->queryFilter = array_merge_recursive($this->queryFilter, $data);
        return $this;
    }

    /**
     * Сортировка сущностей
     *
     * @param string $name
     * @param string $direction
     * @return OdataContainer
     */
    public function orderBy(string $name, string $direction = 'asc')
    {
        $this->queryOrderBy = sprintf('%s %s', $name, $direction);
        return $this;
    }

    /**
     * Признак запроса метаданных
     * @return OdataContainer
     */
    public function metadata()
    {
        $this->queryMetadata = true;
        return $this;
    }

    /**
     * @param int $quantity
     * @return OdataContainer
     */
    public function top(int $quantity)
    {
        $this->top = $quantity;
        return $this;
    }

    /**
     * Получение свойств сущности
     *
     * @param string|null $guid
     * @return array|null
     * @throws GuidValidationException
     */
    public function get(string $guid = null)
    {
        if (!Guid::is_valid($guid)) {
            throw new GuidValidationException();
        }

        $request = $this->name;
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
        $request = $this->connection->url . $request;

        if (!empty($options)) {
            $options = array_merge_recursive($this->connection->options, $options);
        }

        if (!empty($this->querySelect)) {
            $options['query']['$select'] = implode(',', $this->querySelect);
        }

        if (!empty($this->queryExpand)) {
            $options['query']['$expand'] = implode(',', $this->queryExpand);
        }

        if (!empty($this->queryFilter)) {
            $options['query']['$filter'] = implode(' and ', $this->queryFilter);
        }

        if (!empty($this->queryOrderBy)) {
            $options['query']['$orderby'] = $this->queryOrderBy;
        }

        if (!empty($this->top)) {
            $options['query']['$top'] = $this->top;
        }

        $format = 'application/json';
        if (!$this->queryMetadata) {
            $format .= ';odata=nometadata';
            $options['query']['$format'] = $format;
        }

        $result = true;
        try {
            $this->response = new OdataResponse(
                $this->connection->client->request($method, $request, $options)
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
}