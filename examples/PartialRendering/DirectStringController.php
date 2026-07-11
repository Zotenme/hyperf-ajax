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

namespace App\Controller;

use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;

final class DirectStringController extends HyperfAjaxController
{
    public function onRefresh(): AjaxResponse
    {
        return $this->ajax()->partial(
            'profile/message',
            '<div data-ajax-partial="profile/message">Updated without a view engine</div>'
        );
    }
}
