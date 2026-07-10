<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Component;

use ArrayIterator;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Support\AjaxHelpers;
use IteratorAggregate;
use Traversable;

class ComponentContainer implements IteratorAggregate
{
    /**
     * @var list<class-string>
     */
    public static array $globalComponents = [];

    /**
     * @var array<string, object>
     */
    protected array $components = [];

    public function __construct(
        protected AjaxControllerInterface $controller
    ) {
    }

    public function register(): void
    {
        if (property_exists($this->controller, 'components') && is_array($this->controller->components)) {
            foreach ($this->controller->components as $componentClass) {
                $componentClass::createIn($this->controller)->bindToController();
            }
        }

        foreach (static::$globalComponents as $componentClass) {
            $componentClass::createIn($this->controller)->bindToController();
        }
    }

    public function boot(): void
    {
        foreach ($this->components as $component) {
            if (method_exists($component, 'boot')) {
                $component->boot();
            }
        }
    }

    public function bind(string $alias, object $instance): void
    {
        $this->components[$alias] = $instance;

        if (property_exists($instance, 'components') && is_array($instance->components)) {
            foreach ($instance->components as $componentClass) {
                $componentClass::createIn($this->controller)->bindToController();
            }
        }
    }

    public function make(string $alias): ?object
    {
        return $this->components[$alias] ?? null;
    }

    /**
     * @return array{0: object, 1: string}|null
     */
    public function getAjaxHandlerMethod(string $handler): ?array
    {
        foreach ($this->components as $component) {
            if (AjaxHelpers::methodExists($component, $handler)) {
                return [$component, $handler];
            }
        }

        return null;
    }

    public function __get(string $key): ?object
    {
        return $this->make($key);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->components);
    }
}
