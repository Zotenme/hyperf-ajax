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

namespace Zotenme\HyperfAjax\Support;

use Psr\Container\ContainerInterface;
use Zotenme\HyperfAjax\Contracts\AjaxHandlerInvokerInterface;

class MethodInvoker implements AjaxHandlerInvokerInterface
{
    public function __construct(
        protected ContainerInterface $container
    ) {}

    /**
     * @param array<array-key, mixed> $parameters
     */
    public function invoke(callable $callable, array $parameters = []): mixed
    {
        $reflection = is_array($callable)
            ? new \ReflectionMethod($callable[0], $callable[1])
            : new \ReflectionFunction(\Closure::fromCallable($callable));

        return $callable(...$this->resolveArguments(
            $reflection->getParameters(),
            $parameters,
            $reflection->getName()
        ));
    }

    /**
     * @param list<\ReflectionParameter> $reflectedParameters
     * @param array<array-key, mixed> $parameters
     * @return list<mixed>
     */
    protected function resolveArguments(
        array $reflectedParameters,
        array $parameters,
        string $callableName
    ): array {
        $arguments = [];

        foreach ($reflectedParameters as $index => $parameter) {
            $name = $parameter->getName();

            if ($parameter->isVariadic()) {
                $arguments = [...$arguments, ...$this->resolveVariadic($parameter, $parameters, $index)];
                continue;
            }

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            if (array_key_exists($index, $parameters)) {
                $arguments[] = $parameters[$index];
                continue;
            }

            $type = $parameter->getType();
            if (
                $type instanceof \ReflectionNamedType
                && ! $type->isBuiltin()
                && ! enum_exists($type->getName())
                && $this->container->has($type->getName())
            ) {
                $arguments[] = $this->container->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                'Unable to resolve required parameter $%s of AJAX handler %s().',
                $name,
                $callableName
            ));
        }

        return $arguments;
    }

    /**
     * @param array<array-key, mixed> $parameters
     * @return list<mixed>
     */
    protected function resolveVariadic(
        \ReflectionParameter $parameter,
        array $parameters,
        int $parameterIndex
    ): array {
        $name = $parameter->getName();
        if (array_key_exists($name, $parameters)) {
            $values = $parameters[$name];
            if (! is_array($values)) {
                throw new \InvalidArgumentException(sprintf(
                    'Variadic parameter $%s must be provided as an array.',
                    $name
                ));
            }

            return array_values($values);
        }

        $values = [];
        foreach ($parameters as $key => $value) {
            if (is_int($key) && $key >= $parameterIndex) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
