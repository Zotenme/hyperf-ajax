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

use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

final class PlainPhpPartialRenderer implements PartialRendererInterface
{
    public function render(string $partial, object $controller, AjaxRequest $request, array $data = []): string
    {
        $template = match ($partial) {
            'profile/message' => __DIR__ . '/views/profile-message.phtml',
            default => throw new \InvalidArgumentException("Unknown AJAX partial [{$partial}]."),
        };

        $data['ajaxController'] = $controller;
        $data['ajaxRequest'] = $request;

        return $this->renderFile($template, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $template;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }
    }
}
