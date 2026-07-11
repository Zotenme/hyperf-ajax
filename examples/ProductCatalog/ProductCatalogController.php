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

namespace App\Controller;

use App\Catalog\ProductCatalog;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\View\RenderInterface;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;

final class ProductCatalogController extends HyperfAjaxController
{
    public function index(
        RequestInterface $request,
        ResponseInterface $response,
        RenderInterface $view,
        ProductCatalog $catalog
    ): mixed {
        return $this->ajaxPage(
            $request,
            $response,
            fn () => $view->render('catalog.index', [
                'catalog' => $catalog->page($this->filters($request->getQueryParams())),
            ]),
            'index'
        );
    }

    public function onFilter(ProductCatalog $catalog): AjaxResponse
    {
        $this->withAjaxPartialData([
            'catalog' => $catalog->page($this->filters($this->ajaxAll())),
        ]);

        return $this->ajax()->data(['filtered' => true]);
    }

    public function onLoadMore(ProductCatalog $catalog): AjaxResponse
    {
        $page = max(2, (int) $this->ajaxPost('page', 2));
        $this->withAjaxPartialData([
            'catalog' => $catalog->page($this->filters($this->ajaxAll()), $page),
        ]);

        return $this->ajax()->data(['page' => $page]);
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array{category: string, minPrice: int}
     */
    private function filters(array $input): array
    {
        return [
            'category' => trim((string) ($input['category'] ?? '')),
            'minPrice' => max(0, (int) ($input['min_price'] ?? 0)),
        ];
    }
}
