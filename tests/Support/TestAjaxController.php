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

namespace Zotenme\HyperfAjax\Tests\Support;

use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;
use Zotenme\HyperfAjax\Support\AjaxExecutionContext;

class TestAjaxController extends HyperfAjaxController
{
    public function activate(AjaxRequest $request): void
    {
        $this->setAjaxExecutionContext(new AjaxExecutionContext($request));
    }

    public function release(): void
    {
        $this->clearAjaxExecutionContext();
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    public function dispatchAjaxRequest(AjaxRequest $request, string $action = '', array $parameters = []): AjaxResponse
    {
        $this->activate($request);

        try {
            $this->initAjaxComponents();

            return $this->runAjaxAction($action, $parameters);
        } finally {
            $this->release();
        }
    }
}
