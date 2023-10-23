<?php

namespace OData\Client\Exception;

use Exception;

class CorruptObjectNameException extends Exception {
    public function __construct()
    {
        parent::__construct('Некорректное имя сущности 1С');
    }
}