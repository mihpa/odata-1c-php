<?php

namespace OData\Client;

class Guid
{
    /**
     * @param string|null $guid
     * @return bool
     */
    public static function is_valid(string $guid = null): bool
    {
        if (is_null($guid)) {
            return true;
        }

        return preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $guid);
    }
}