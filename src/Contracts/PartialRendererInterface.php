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

namespace Zotenme\HyperfAjax\Contracts;

use Zotenme\HyperfAjax\AjaxRequest;

interface PartialRendererInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(
        string $partial,
        object $controller,
        AjaxRequest $request,
        array $data = []
    ): string;
}
