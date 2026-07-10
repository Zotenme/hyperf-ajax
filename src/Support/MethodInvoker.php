<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Support;

use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

class MethodInvoker
{
    public function __construct(
        protected mixed $container = null
    ) {
    }

    public function invoke(callable|array $callable, array $parameters = []): mixed
    {
        if (array_is_list($parameters) && $this->container === null) {
            return $callable(...$parameters);
        }

        $reflection = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction($callable);

        return $callable(...$this->resolveArguments($reflection->getParameters(), $parameters));
    }

    protected function resolveArguments(array $reflectedParameters, array $parameters): array
    {
        $arguments = [];

        foreach ($reflectedParameters as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();
            if (
                $type instanceof ReflectionNamedType
                && ! $type->isBuiltin()
                && $this->container instanceof ContainerInterface
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

            $arguments[] = $parameters[$name] ?? null;
        }

        return $arguments;
    }
}
