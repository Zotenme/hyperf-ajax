<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Contracts;

interface AjaxControllerInterface
{
    public function addComponentInstance(string $alias, ViewComponentInterface $instance): void;

    public function getComponentInstance(string $alias): ?ViewComponentInterface;
}
