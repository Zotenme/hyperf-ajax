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

namespace Zotenme\HyperfAjax\Component;

use Hyperf\Contract\ContainerInterface;
use Zotenme\HyperfAjax\AjaxResponseFactory;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;

final class ViewComponentFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly AjaxResponseFactory $responseFactory = new AjaxResponseFactory()
    ) {}

    /**
     * @param class-string $componentClass
     * @param array<string, mixed> $config
     */
    public function make(
        string $componentClass,
        AjaxControllerInterface $controller,
        array $config = []
    ): ViewComponentInterface {
        $component = $this->container->make($componentClass);
        if (! $component instanceof ViewComponentInterface) {
            throw new \RuntimeException("Component [{$componentClass}] must implement ViewComponentInterface.");
        }

        $component->setController($controller);
        $component->setResponseFactory($this->responseFactory);
        $component->setConfig($config);
        $component->setAlias($config['alias'] ?? basename(str_replace('\\', '/', $componentClass)));

        return $component;
    }
}
