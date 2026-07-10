<?php

declare(strict_types=1);

use Zotenme\HyperfAjax\AjaxResponse;

if (! function_exists('ajax')) {
    function ajax(): AjaxResponse
    {
        return new AjaxResponse();
    }
}
