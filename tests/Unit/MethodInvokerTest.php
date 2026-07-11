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

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testNamedParametersTakePriorityOverContainerDependencies(): void
    {
        $explicit = new InjectedService();
        $container = new TestContainer([
            InjectedService::class => new InjectedService(),
        ]);

        $result = (new MethodInvoker($container))->invoke(
            static fn (InjectedService $service): InjectedService => $service,
            ['service' => $explicit]
        );

        self::assertSame($explicit, $result);
    }

    public function testResolvesPositionalDefaultNullableAndVariadicParameters(): void
    {
        $handler = static fn (
            string $required,
            string $default = 'default',
            ?string $nullable = null,
            string ...$tags
        ): array => [$required, $default, $nullable, $tags];

        $invoker = new MethodInvoker(new TestContainer());

        self::assertSame(
            ['first', 'second', null, ['a', 'b']],
            $invoker->invoke($handler, ['required' => 'first', 'default' => 'second', 'tags' => ['a', 'b']])
        );
        self::assertSame(
            ['first', 'second', null, ['a', 'b']],
            $invoker->invoke($handler, ['first', 'second', null, 'a', 'b'])
        );
    }

    public function testAcceptsExplicitUnionAndEnumValues(): void
    {
        $handler = static fn (int|string $id, InvocationState $state): array => [$id, $state];

        $result = (new MethodInvoker(new TestContainer()))->invoke($handler, [
            'id' => 42,
            'state' => InvocationState::Ready,
        ]);

        self::assertSame([42, InvocationState::Ready], $result);
    }

    #[DataProvider('unresolvableSignatureProvider')]
    public function testRejectsUnresolvableRequiredParameters(callable $handler, string $parameter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unable to resolve required parameter \${$parameter}");

        (new MethodInvoker(new TestContainer()))->invoke($handler);
    }

    /** @return iterable<string, array{callable, string}> */
    public static function unresolvableSignatureProvider(): iterable
    {
        yield 'builtin' => [static fn (string $id): string => $id, 'id'];
        yield 'union' => [static fn (int|string $id): int|string => $id, 'id'];
        yield 'intersection' => [static fn (FirstContract&SecondContract $service): object => $service, 'service'];
        yield 'enum' => [static fn (InvocationState $state): InvocationState => $state, 'state'];
        yield 'missing dependency' => [static fn (FirstContract $service): FirstContract => $service, 'service'];
    }

    public function testRejectsNonArrayNamedVariadicValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Variadic parameter $tags must be provided as an array.');

        (new MethodInvoker(new TestContainer()))->invoke(
            static fn (string ...$tags): array => $tags,
            ['tags' => 'invalid']
        );
    }
}

interface FirstContract {}

interface SecondContract {}

enum InvocationState
{
    case Ready;
}
