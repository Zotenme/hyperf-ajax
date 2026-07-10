<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Contracts;

interface ViewComponentInterface
{
    public static function createIn(AjaxControllerInterface $controller, array $config = []): ViewComponentInterface;

    public function bindToController(): void;
}
