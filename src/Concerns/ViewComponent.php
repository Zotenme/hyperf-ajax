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

use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\AjaxResponseFactory;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;

trait ViewComponent
{
    /** @var array<string, mixed> */
    public array $config = [];

    public string $alias = '';

    public ?AjaxControllerInterface $controller = null;

    protected ?AjaxResponseFactory $responseFactory = null;

    public function ajax(): AjaxResponse
    {
        return ($this->responseFactory ??= new AjaxResponseFactory())->create();
    }

    public function setResponseFactory(AjaxResponseFactory $responseFactory): void
    {
        $this->responseFactory = $responseFactory;
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

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
