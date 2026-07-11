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

namespace App\Ajax;

use Hyperf\View\RenderInterface;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

final class HyperfViewPartialRenderer implements PartialRendererInterface
{
    public function __construct(private readonly RenderInterface $view) {}

    public function render(string $partial, object $controller, AjaxRequest $request, array $data = []): string
    {
        $view = match ($partial) {
            'profile/message' => 'partials.profile-message',
            default => throw new \InvalidArgumentException("Unknown AJAX partial [{$partial}]."),
        };

        return $this->view->getContents($view, [
            ...$data,
            'ajaxController' => $controller,
            'ajaxRequest' => $request,
        ]);
    }
}
