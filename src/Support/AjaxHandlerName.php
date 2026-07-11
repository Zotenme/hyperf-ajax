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

final class AjaxHandlerName
{
    public const GRAMMAR = '/^(?:[a-zA-Z0-9_]+::)?on[A-Z][a-zA-Z0-9_]*$/D';

    public static function isValid(string $qualifiedHandler): bool
    {
        return preg_match(self::GRAMMAR, $qualifiedHandler) === 1;
    }
}
