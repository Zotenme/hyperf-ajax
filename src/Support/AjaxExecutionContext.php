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

use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Component\ComponentContainer;

final class AjaxExecutionContext
{
    public function __construct(
        public readonly AjaxRequest $request,
        public ?ComponentContainer $components = null
    ) {}
}
