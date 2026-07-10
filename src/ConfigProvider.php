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

namespace Zotenme\HyperfAjax;

class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $basePath = (string) constant('BASE_PATH');

        return [
            'dependencies' => [],
            'publish' => [
                [
                    'id' => 'assets',
                    'description' => 'Hyperf Ajax frontend assets.',
                    'source' => __DIR__ . '/../resources/dist',
                    'destination' => $basePath . '/public/vendor/hyperfajax',
                ],
            ],
        ];
    }
}
