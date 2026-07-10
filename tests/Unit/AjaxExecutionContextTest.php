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
use Swoole\Coroutine;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;
use Zotenme\HyperfAjax\Tests\Support\TestAjaxController;
use Zotenme\HyperfAjax\Tests\Support\TestContainer;

/**
 * @internal
 * @coversNothing
 */
class AjaxExecutionContextTest extends TestCase
{
    public function testBaseControllerConstructorIsCompatibleWithHyperfProxies(): void
    {
        $constructor = new \ReflectionMethod(HyperfAjaxController::class, '__construct');

        self::assertSame(0, $constructor->getNumberOfRequiredParameters());
    }

    public function testContextIsIsolatedAndCanBeCleared(): void
    {
        if (! method_exists(Coroutine::class, 'setTestCid')) {
            self::markTestSkipped('The test double is only available outside a real Swoole runtime.');
        }

        ApplicationContext::setContainer(new TestContainer());
        $controller = new TestAjaxController();
        $firstRequest = new AjaxRequest();
        $firstRequest->handler = 'onFirst';
        $secondRequest = new AjaxRequest();
        $secondRequest->handler = 'onSecond';

        Coroutine::setTestCid(1);
        $controller->activate($firstRequest);
        Coroutine::setTestCid(2);
        $controller->activate($secondRequest);

        $activeRequest = $controller->getAjaxRequest();
        self::assertInstanceOf(AjaxRequest::class, $activeRequest);
        self::assertSame('onSecond', $activeRequest->handler);

        Coroutine::setTestCid(1);
        $activeRequest = $controller->getAjaxRequest();
        self::assertInstanceOf(AjaxRequest::class, $activeRequest);
        self::assertSame('onFirst', $activeRequest->handler);
        $controller->release();
        self::assertNull($controller->getAjaxRequest());

        Coroutine::setTestCid(2);
        $activeRequest = $controller->getAjaxRequest();
        self::assertInstanceOf(AjaxRequest::class, $activeRequest);
        self::assertSame('onSecond', $activeRequest->handler);
        $controller->release();
        Coroutine::setTestCid(-1);
    }
}
