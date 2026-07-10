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

namespace Zotenme\HyperfAjax\Concerns;

use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;

trait ViewComponent
{
    /** @var array<string, mixed> */
    public array $config = [];

    public string $alias = '';

    public ?AjaxControllerInterface $controller = null;

    /**
     * @param array<string, mixed> $config
     */
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
            throw new \RuntimeException('Component [' . static::class . '] has no controller specified.');
        }

        $this->controller->addComponentInstance($this->alias, $this);
    }

    public function getController(): ?AjaxControllerInterface
    {
        return $this->controller;
    }

    public function setController(AjaxControllerInterface $controller): void
    {
        $this->controller = $controller;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): void
    {
        $this->alias = $alias;
    }
}
