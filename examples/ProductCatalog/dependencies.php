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
use App\Ajax\CatalogPartialRenderer;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

return [
    PartialRendererInterface::class => CatalogPartialRenderer::class,
];
