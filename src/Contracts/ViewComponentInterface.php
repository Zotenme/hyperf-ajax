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

namespace Zotenme\HyperfAjax\Contracts;

interface ViewComponentInterface
{
    public function bindToController(): void;

    public function getController(): ?AjaxControllerInterface;

    public function setController(AjaxControllerInterface $controller): void;

    public function getAlias(): string;

    public function setAlias(string $alias): void;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void;
}
