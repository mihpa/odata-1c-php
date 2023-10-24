# OData 1С 8.3 PHP

Библиотека доступа к 1С:Предприятие 8.3 через протокол OData. Язык программирования PHP.

Официальная документация доступна [по адресу](https://its.1c.ru/db/v8322doc#bookmark:dev:TI000001358),
актуальная версия на момент публикации Платформа 1С:Предприятие 8.3.22.

## Установка библиотеки через Composer
``` bash
$ composer require mihpa/odata-1c-php
```

## Использование библиотеки

### Инициализация
```php
use OData\Client\OdataConnection;

$client = new OdataConnection('http://<имя хоста OData>/<имя базы данных>/odata/standard.odata/');

// Если используется дайджест-проверки подлинности IIS
$client->setAuth('<имя пользователя>', '<пароль>');

// Если подключение должно происходить через прокси-сервер
$client->setProxy('<имя хоста прокси>', '<номер порта>');

// Если требуется увеличить время ожидания отклика (по умолчанию 300 секунд)
$client->setTimeout(600);
```

### Получение информации
```php
$customer = $client->{'Справочник/Контрагенты'}
    ->get('9ffb357b-8431-11ec-8105-005056baf506');
```

### Использование фильтра
```php
$documents = $client->{'Документ/ПоступлениеТоваровУслуг'}
    ->select([
        'Ref_Key',
        'DataVersion',
        'Number',
        'Date',
        'Posted',
        'ВидОперации',
        'Товары/Сумма',
        'Товары/СуммаНДС',
        'Услуги/Сумма',
        'Контрагент/Ref_Key',
        'Контрагент/Description',
        'ДоговорКонтрагента/Номер',
        'ДоговорКонтрагента/Дата',
    ])
    ->expand([
        'Контрагент',
        'ДоговорКонтрагента',
    ])
    ->filter('not (DeletionMark)')
    ->orderby('Date', 'desc')
    ->get();
```

### Получение части информации
```php
$documents = $client->{'Документ/ПоступлениеТоваровУслуг'}
    ->top(50)
    ->offset(200)
    ->get();
```

### Создать
```php
$customer = $client->{'Справочник/Контрагенты'}
    ->create(
        [
            'Description'=>'Тестовый контрагент',
        ]
    );
```

### Изменить
```php
$customer = $client->{'Справочник/Контрагенты'}
    ->update(
        [
            'Description'=>'Изменённый контрагент',
        ],
        'c70666f7-ae3a-11e5-80ce-005056baf506'
    );
```

### Пометить на удаление
```php
$customer = $client->{'Справочник/Контрагенты'}
    ->delete('c70666f7-ae3a-11e5-80ce-005056baf506');
```

### Снять пометку на удаление
```php
$customer = $client->{'Справочник/Контрагенты'}
    ->undelete('c70666f7-ae3a-11e5-80ce-005056baf506');
```

### Удалить навсегда
```php
$client->{'Справочник/Контрагенты'}
    ->deletePermanently('c70666f7-ae3a-11e5-80ce-005056baf506');
```

### Провести
```php
$document = $client->{'Документ/ПоступлениеТоваровУслуг'}
    ->post('08e15d09-d48e-11ed-a84a-0050569ad97e');
```

### Отменить проведение
```php
$document = $client->{'Документ/ПоступлениеТоваровУслуг'}
    ->unpost('08e15d09-d48e-11ed-a84a-0050569ad97e');
```
