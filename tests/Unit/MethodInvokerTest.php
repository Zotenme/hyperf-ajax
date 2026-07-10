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

use PHPUnit\Framework\TestCase;
use Zotenme\HyperfAjax\Support\MethodInvoker;
use Zotenme\HyperfAjax\Tests\Support\InjectedService;
use Zotenme\HyperfAjax\Tests\Support\TestContainer;

/**
 * @internal
 * @coversNothing
 */
class MethodInvokerTest extends TestCase
{
    public function testResolvesNamedParameters(): void
    {
        $controller = new class {
            public function onSave(string $id = 'x'): string
            {
                return 'saved:' . $id;
            }
        };

        $result = (new MethodInvoker(new TestContainer()))->invoke([$controller, 'onSave'], ['id' => '42']);

        self::assertSame('saved:42', $result);
    }

    public function testResolvesTypedDependencies(): void
    {
        $service = new InjectedService();
        $container = new TestContainer([
            InjectedService::class => $service,
        ]);
        $controller = new class {
            public function onInjected(InjectedService $service): string
            {
                return $service->value();
            }
        };

        $result = (new MethodInvoker($container))->invoke([$controller, 'onInjected']);

        self::assertSame('injected', $result);
    }
}
