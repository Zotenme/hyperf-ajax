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
use Zotenme\HyperfAjax\Component\ComponentContainer;
use Zotenme\HyperfAjax\Concerns\ViewComponent;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;
use Zotenme\HyperfAjax\Exception\ComponentNotFound;
use Zotenme\HyperfAjax\Exception\HandlerNotFound;
use Zotenme\HyperfAjax\Tests\Support\TestAjaxController;
use Zotenme\HyperfAjax\Tests\Support\TestContainer;

/**
 * @internal
 * @coversNothing
 */
class ComponentDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        ParentComponent::$bootCount = 0;
        NestedComponent::$bootCount = 0;
        ApplicationContext::setContainer(new TestContainer());
    }

    public function testDoesNotExposeMutableGlobalComponentState(): void
    {
        self::assertFalse(property_exists(ComponentContainer::class, 'globalComponents'));
    }

    public function testDispatchesQualifiedComponentHandlerAndBootsRegisteredComponents(): void
    {
        $response = $this->controller()->dispatchAjaxRequest($this->request('ParentComponent::onQualified'));

        self::assertSame('qualified', $response->toArray()['result']);
        self::assertSame(1, ParentComponent::$bootCount);
        self::assertSame(1, NestedComponent::$bootCount);
    }

    public function testFallsBackToHandlerOnNestedComponent(): void
    {
        $response = $this->controller()->dispatchAjaxRequest($this->request('onNested'));

        self::assertSame('nested', $response->toArray()['result']);
    }

    public function testPrefersControllerHandlerOverComponentFallback(): void
    {
        $response = $this->controller()->dispatchAjaxRequest($this->request('onShared'));

        self::assertSame('controller', $response->toArray()['result']);
    }

    public function testPrefersActionSpecificControllerHandler(): void
    {
        $response = $this->controller()->dispatchAjaxRequest($this->request('onShared'), 'index');

        self::assertSame('action', $response->toArray()['result']);
    }

    public function testThrowsForMissingQualifiedComponent(): void
    {
        $this->expectException(ComponentNotFound::class);
        $this->expectExceptionMessage('Component name [Missing] not found');

        $this->controller()->dispatchAjaxRequest($this->request('Missing::onQualified'));
    }

    public function testThrowsForMissingHandler(): void
    {
        $this->expectException(HandlerNotFound::class);
        $this->expectExceptionMessage('AJAX handler [onMissing] not found');

        $this->controller()->dispatchAjaxRequest($this->request('onMissing'));
    }

    public function testThrowsForMissingHandlerOnExistingComponent(): void
    {
        $this->expectException(HandlerNotFound::class);
        $this->expectExceptionMessage('AJAX handler [onMissing] is not callable');

        $this->controller()->dispatchAjaxRequest($this->request('ParentComponent::onMissing'));
    }

    public function testRejectsDuplicateAliases(): void
    {
        $controller = new ComponentController();
        $controller->components = [ParentComponent::class, ParentComponent::class];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AJAX component alias [ParentComponent] is already registered.');

        $controller->dispatchAjaxRequest($this->request('onShared'));
    }

    private function controller(): ComponentController
    {
        return new ComponentController();
    }

    private function request(string $qualifiedHandler): AjaxRequest
    {
        $request = new AjaxRequest();
        $request->qualifiedHandler = $qualifiedHandler;
        if (str_contains($qualifiedHandler, '::')) {
            [$request->component, $request->handler] = explode('::', $qualifiedHandler, 2);
        } else {
            $request->handler = $qualifiedHandler;
        }

        return $request;
    }
}

class ComponentController extends TestAjaxController
{
    /** @var list<class-string> */
    public array $components = [ParentComponent::class];

    public function onShared(): AjaxResponse
    {
        return $this->ajax()->data(['result' => 'controller']);
    }

    public function index_onShared(): string
    {
        return 'action';
    }
}

class ParentComponent implements ViewComponentInterface
{
    use ViewComponent;

    public static int $bootCount = 0;

    /** @var list<class-string> */
    public array $components = [NestedComponent::class];

    public function boot(): void
    {
        ++self::$bootCount;
    }

    public function onQualified(): string
    {
        return 'qualified';
    }

    public function onShared(): string
    {
        return 'component';
    }
}

class NestedComponent implements ViewComponentInterface
{
    use ViewComponent;

    public static int $bootCount = 0;

    public function boot(): void
    {
        ++self::$bootCount;
    }

    public function onNested(): string
    {
        return 'nested';
    }
}
