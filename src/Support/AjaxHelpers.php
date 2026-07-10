<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf Ajax.
 *
 * @link     https://github.com/Zotenme/hyperf-ajax
 * @document https://github.com/Zotenme/hyperf-ajax/blob/main/README.md
 * @contact  zotenme@gmail.com
 * @license  https://github.com/Zotenme/hyperf-ajax/blob/main/LICENSE.md
 */

namespace Zotenme\HyperfAjax\Support;

final class AjaxHelpers
{
    /**
     * @param array<array-key, mixed> $array
     */
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
