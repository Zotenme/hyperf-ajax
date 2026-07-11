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
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpServer\Contract\ResponseInterface;
use PHPUnit\Framework\TestCase;
use Zotenme\HyperfAjax\Contracts\AjaxHandlerInvokerInterface;
use Zotenme\HyperfAjax\Tests\Support\TestAjaxController;
use Zotenme\HyperfAjax\Tests\Support\TestContainer;

/**
 * @internal
 * @coversNothing
 */
class ControllerIntegrationApiTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationContext::setContainer(new TestContainer());
    }

    public function testAjaxPageRendersNormallyForNonAjaxRequest(): void
    {
        $controller = new AjaxPageController();

        $result = $controller->ajaxPage(
            new Request('GET', '/profile'),
            $this->response(),
            static fn (): string => 'normal page',
            'index'
        );

        self::assertSame('normal page', $result);
        self::assertNull($controller->getAjaxRequest());
    }

    public function testAjaxPageDispatchesActionHandlerWithoutCallingRenderCallback(): void
    {
        $controller = new AjaxPageController();
        $renderCalled = false;
        $request = new Request('POST', '/profile', [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-AJAX-HANDLER' => 'onPing',
        ]);

        $result = $controller->ajaxPage(
            $request,
            $this->response(),
            static function () use (&$renderCalled): string {
                $renderCalled = true;
                return 'must not render';
            },
            'index',
            ['name' => 'Hyperf']
        );

        self::assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $result);
        self::assertFalse($renderCalled);
        self::assertSame('pong, Hyperf', json_decode(
            (string) $result->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR
        )['result']);
        self::assertNull($controller->getAjaxRequest());
    }

    public function testUsesHandlerInvokerBoundInContainer(): void
    {
        $invoker = new class implements AjaxHandlerInvokerInterface {
            public function invoke(callable $handler, array $parameters = []): mixed
            {
                return 'custom invocation';
            }
        };
        ApplicationContext::setContainer(new TestContainer([
            AjaxHandlerInvokerInterface::class => $invoker,
        ]));
        $request = new Request('POST', '/profile', [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-AJAX-HANDLER' => 'onPing',
        ]);

        $result = (new AjaxPageController())->ajaxPage(
            $request,
            $this->response(),
            static fn (): string => 'must not render',
            'index'
        );

        self::assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $result);
        self::assertSame('custom invocation', json_decode(
            (string) $result->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR
        )['result']);
    }

    private function response(): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('json')->willReturnCallback(
            static fn (array $data): Response => (new Response())->withContent(json_encode(
                $data,
                JSON_THROW_ON_ERROR
            ))
        );

        return $response;
    }
}

class AjaxPageController extends TestAjaxController
{
    public function index_onPing(string $name): string
    {
        return 'pong, ' . $name;
    }
}
