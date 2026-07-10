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

use Hyperf\Contract\ContainerInterface;

class TestContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $entries = [];

    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? $this->make($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) || class_exists($id);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function make(string $name, array $parameters = []): mixed
    {
        if (! class_exists($name)) {
            throw new \RuntimeException("Class [{$name}] not found.");
        }

        /** @var class-string $name */
        $reflection = new \ReflectionClass($name);
        $constructor = $reflection->getConstructor();
        if (! $constructor) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $parameters)) {
                $arguments[] = $parameters[$parameter->getName()];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
                continue;
            }

            $arguments[] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : null;
        }

        return $reflection->newInstanceArgs($arguments);
    }

    public function set(string $name, mixed $entry): void
    {
        $this->entries[$name] = $entry;
    }

    public function unbind(string $name): void
    {
        unset($this->entries[$name]);
    }

    /**
     * @param array<array-key, mixed>|callable|string $definition
     */
    public function define(string $name, mixed $definition): void
    {
        $this->entries[$name] = $definition;
    }
}
