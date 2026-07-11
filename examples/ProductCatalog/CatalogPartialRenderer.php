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

namespace App\Ajax;

use Hyperf\View\RenderInterface;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

final class CatalogPartialRenderer implements PartialRendererInterface
{
    public function __construct(private readonly RenderInterface $view) {}

    public function render(string $partial, object $controller, AjaxRequest $request, array $data = []): string
    {
        $view = match ($partial) {
            'catalog/product-page' => 'catalog.partials.product-page',
            'catalog/load-more' => 'catalog.partials.load-more',
            'catalog/summary' => 'catalog.partials.summary',
            default => throw new \InvalidArgumentException("Unknown catalog partial [{$partial}]."),
        };

        return $this->view->getContents($view, $data);
    }
}
