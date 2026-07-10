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

namespace Zotenme\HyperfAjax\Controller;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ContainerInterface;
use Zotenme\HyperfAjax\Concerns\InteractsWithAjax;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;

abstract class HyperfAjaxController implements AjaxControllerInterface
{
    use InteractsWithAjax;

    public function __construct() {}

    final protected function getAjaxContainer(): ContainerInterface
    {
        $container = ApplicationContext::getContainer();
        if (! $container instanceof ContainerInterface) {
            throw new \RuntimeException('The Hyperf application container must implement Hyperf\Contract\ContainerInterface.');
        }

        return $container;
    }
}
