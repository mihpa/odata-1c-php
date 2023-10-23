<?php

namespace OData\Client\Exception;

use Exception;

class GuidValidationException extends Exception {
    public function __construct()
    {
        parent::__construct('Ошибка валидации уникального идентификатора (GUID)');
    }
}