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
use Zotenme\HyperfAjax\Component\ViewComponentFactory;
use Zotenme\HyperfAjax\Tests\Support\InjectedService;
use Zotenme\HyperfAjax\Tests\Support\TestAjaxController;
use Zotenme\HyperfAjax\Tests\Support\TestContainer;
use Zotenme\HyperfAjax\Tests\Support\TestViewComponent;

/**
 * @internal
 * @coversNothing
 */
class ViewComponentFactoryTest extends TestCase
{
    public function testInjectsDependenciesAndResponseFactory(): void
    {
        $service = new InjectedService();
        $container = new TestContainer([
            InjectedService::class => $service,
        ]);
        ApplicationContext::setContainer($container);
        $controller = new TestAjaxController();

        $component = (new ViewComponentFactory($container))->make(
            TestViewComponent::class,
            $controller,
            ['alias' => 'injectedForm', 'enabled' => true]
        );

        self::assertInstanceOf(TestViewComponent::class, $component);
        self::assertSame($service, $component->service);
        self::assertSame($controller, $component->getController());
        self::assertSame('injectedForm', $component->getAlias());
        self::assertTrue($component->getConfig()['enabled']);

        $response = $component->ajax()->update(['#message' => 'Saved from component']);
        self::assertSame('#message', $response->getOps()[0]['selector']);
        self::assertNotSame($response, $component->ajax());
    }
}
