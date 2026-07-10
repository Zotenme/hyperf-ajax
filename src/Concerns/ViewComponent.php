<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Concerns;

use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;
use RuntimeException;

trait ViewComponent
{
    public array $config = [];

    public string $alias = '';

    public ?AjaxControllerInterface $controller = null;

    public static function createIn(AjaxControllerInterface $controller, array $config = []): ViewComponentInterface
    {
        $instance = new static();
        $instance->controller = $controller;
        $instance->config = $config;
        $instance->alias = $config['alias'] ?? basename(str_replace('\\', '/', static::class));

        return $instance;
    }

    public function bindToController(): void
    {
        if (! $this->controller instanceof AjaxControllerInterface) {
            throw new RuntimeException('Component [' . static::class . '] has no controller specified.');
        }

        $this->controller->addComponentInstance($this->alias, $this);
    }
}
