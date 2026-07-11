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

namespace Zotenme\HyperfAjax\Tests\Unit;

use Hyperf\Context\ApplicationContext;
use PHPUnit\Framework\TestCase;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;
use Zotenme\HyperfAjax\Support\CallablePartialRenderer;
use Zotenme\HyperfAjax\Tests\Support\TestAjaxController;
use Zotenme\HyperfAjax\Tests\Support\TestContainer;

/**
 * @internal
 * @coversNothing
 */
class PartialRendererTest extends TestCase
{
    public function testRendersRequestedPartialsThroughContainerBinding(): void
    {
        $renderer = new RecordingPartialRenderer();
        $container = new TestContainer([
            PartialRendererInterface::class => $renderer,
        ]);
        ApplicationContext::setContainer($container);

        $request = new AjaxRequest();
        $request->handler = 'onSave';
        $request->partialList = ['profile/message'];

        $controller = new PartialTestController();
        $controller->activate($request);

        try {
            $response = $controller->dispatch();
        } finally {
            $controller->release();
        }

        self::assertSame([
            'op' => AjaxResponse::OP_PARTIAL,
            'name' => 'profile/message',
            'html' => '<div>Saved</div>',
        ], $response->getOps()[0]);
        self::assertSame('profile/message', $renderer->partial);
        self::assertSame($controller, $renderer->controller);
        self::assertSame($request, $renderer->request);
        self::assertSame(['message' => 'Saved'], $renderer->data);
    }

    public function testCallableAdapterForwardsTheCompleteRenderContext(): void
    {
        $request = new AjaxRequest();
        $controller = new \stdClass();
        $renderer = new CallablePartialRenderer(
            static function (string $partial, object $owner, AjaxRequest $ajaxRequest, array $data) use ($controller, $request): string {
                self::assertSame('notice', $partial);
                self::assertSame($controller, $owner);
                self::assertSame($request, $ajaxRequest);
                self::assertSame(['text' => 'Ready'], $data);

                return '<strong>Ready</strong>';
            }
        );

        self::assertSame(
            '<strong>Ready</strong>',
            $renderer->render('notice', $controller, $request, ['text' => 'Ready'])
        );
    }
}

final class PartialTestController extends TestAjaxController
{
    public function onSave(): AjaxResponse
    {
        $this->withAjaxPartialData(['message' => 'Saved']);

        return $this->ajax();
    }

    public function dispatch(): AjaxResponse
    {
        return $this->runAjaxAction('', []);
    }
}

final class RecordingPartialRenderer implements PartialRendererInterface
{
    public string $partial = '';

    public ?object $controller = null;

    public ?AjaxRequest $request = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function render(string $partial, object $controller, AjaxRequest $request, array $data = []): string
    {
        $this->partial = $partial;
        $this->controller = $controller;
        $this->request = $request;
        $this->data = $data;

        return '<div>' . $data['message'] . '</div>';
    }
}
