<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Contracts;

interface AjaxExceptionInterface
{
    public function toAjaxData(): array;
}
