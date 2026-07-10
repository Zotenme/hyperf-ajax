<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Support;

final class AjaxHelpers
{
    public static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public static function methodExists(object $object, string $method): bool
    {
        if (method_exists($object, 'methodExists')) {
            return (bool) $object->methodExists($method);
        }

        return method_exists($object, $method);
    }
}
