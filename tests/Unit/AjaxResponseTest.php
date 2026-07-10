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
use Zotenme\HyperfAjax\AjaxResponse;

/**
 * @internal
 * @coversNothing
 */
class AjaxResponseTest extends TestCase
{
    public function testBuildsLarajaxEnvelope(): void
    {
        $response = (new AjaxResponse())
            ->data(['answer' => 42])
            ->update(['#message' => 'Saved'])
            ->browserEvent('profile:saved', ['ok' => true])
            ->toArray();

        self::assertSame(42, $response['answer']);
        self::assertTrue($response['__ajax']['ok']);
        self::assertSame('patchDom', $response['__ajax']['ops'][0]['op']);
        self::assertSame('#message', $response['__ajax']['ops'][0]['selector']);
        self::assertSame('dispatch', $response['__ajax']['ops'][1]['op']);
    }

    public function testConvertsSelectorShortcutsToDomPatches(): void
    {
        $response = (new AjaxResponse())->dataWithUpdateSelectors([
            '@#list' => '<li>New</li>',
            'count' => 1,
        ])->toArray();

        self::assertSame(1, $response['count']);
        self::assertSame('append', $response['__ajax']['ops'][0]['swap']);
        self::assertSame('#list', $response['__ajax']['ops'][0]['selector']);
    }
}
