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

namespace Zotenme\HyperfAjax\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zotenme\HyperfAjax\ConfigProvider;
use Zotenme\HyperfAjax\Contracts\AjaxHandlerInvokerInterface;
use Zotenme\HyperfAjax\Support\MethodInvoker;

/**
 * @internal
 * @coversNothing
 */
class ConfigProviderTest extends TestCase
{
    private const PUBLIC_ASSET_PATH = '/vendor/hyperfajax/framework-bundle.min.js';

    public static function setUpBeforeClass(): void
    {
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', '/tmp/hyperf-ajax-test-app');
        }
    }

    public function testPublishesBundleToDocumentedPublicPath(): void
    {
        $config = (new ConfigProvider())();
        $publication = $config['publish'][0];

        self::assertSame(
            constant('BASE_PATH') . '/public/vendor/hyperfajax',
            $publication['destination']
        );
        self::assertFileExists($publication['source'] . '/framework-bundle.min.js');
    }

    public function testRegistersDefaultHandlerInvoker(): void
    {
        $config = (new ConfigProvider())();

        self::assertSame(
            MethodInvoker::class,
            $config['dependencies'][AjaxHandlerInvokerInterface::class]
        );
    }

    public function testReadmeAndCopyableExampleUsePublishedBundleUrl(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertStringContainsString(
            self::PUBLIC_ASSET_PATH,
            (string) file_get_contents($root . '/README.md')
        );
        self::assertStringContainsString(
            self::PUBLIC_ASSET_PATH,
            (string) file_get_contents($root . '/examples/HyperfAjaxTestController.php')
        );
    }
}
