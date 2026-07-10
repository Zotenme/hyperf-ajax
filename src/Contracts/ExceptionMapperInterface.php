<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Contracts;

use Zotenme\HyperfAjax\AjaxResponse;
use Throwable;

interface ExceptionMapperInterface
{
    public function map(Throwable $exception): AjaxResponse;
}
