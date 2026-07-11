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
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

final class CallablePartialRenderer implements PartialRendererInterface
{
    private readonly \Closure $renderer;

    public function __construct(callable $renderer)
    {
        $this->renderer = $renderer(...);
    }

    public function render(
        string $partial,
        object $controller,
        AjaxRequest $request,
        array $data = []
    ): string {
        return ($this->renderer)($partial, $controller, $request, $data);
    }
}
