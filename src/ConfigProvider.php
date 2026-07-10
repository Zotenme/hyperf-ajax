<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'Hyperf Ajax configuration.',
                    'source' => __DIR__ . '/../publish/hyperfajax.php',
                    'destination' => BASE_PATH . '/config/autoload/hyperfajax.php',
                ],
                [
                    'id' => 'assets',
                    'description' => 'Hyperf Ajax frontend assets.',
                    'source' => __DIR__ . '/../resources/dist',
                    'destination' => BASE_PATH . '/public/vendor/hyperfajax',
                ],
            ],
        ];
    }
}
