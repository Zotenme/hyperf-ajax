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

use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpServer\Contract\ResponseInterface;
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

    public function testAddsExplicitFlashOperation(): void
    {
        $operation = (new AjaxResponse())
            ->flash('success', 'Profile saved')
            ->toArray()['__ajax']['ops'][0];

        self::assertSame([
            'op' => AjaxResponse::OP_FLASH,
            'level' => 'success',
            'text' => 'Profile saved',
        ], $operation);
    }

    public function testReturnsForcedPsrResponseWithoutConversion(): void
    {
        $forced = (new Response())
            ->withStatus(202)
            ->withHeader('X-Forced', 'yes');

        $actual = AjaxResponse::wrap($forced)->toPsrResponse($this->createStub(
            ResponseInterface::class
        ));

        self::assertSame($forced, $actual);
        self::assertSame(202, $actual->getStatusCode());
        self::assertSame('yes', $actual->getHeaderLine('X-Forced'));
    }

    public function testReplacesExistingContentTypeWithSingleJsonHeader(): void
    {
        $jsonResponse = (new Response())
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withAddedHeader('Content-Type', 'application/json; charset=utf-8');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())
            ->method('json')
            ->willReturn($jsonResponse);

        $actual = (new AjaxResponse())->toPsrResponse($response);

        self::assertSame(
            ['application/json; charset=utf-8'],
            $actual->getHeader('Content-Type')
        );
    }

    public function testRejectsUnsupportedHandlerResult(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('AJAX handler returned unsupported result of type [stdClass].');

        AjaxResponse::wrap(new \stdClass());
    }

    public function testWrapsSupportedScalarAndArrayResults(): void
    {
        self::assertSame('saved', AjaxResponse::wrap('saved')->toArray()['result']);
        self::assertSame(42, AjaxResponse::wrap(42)->toArray()['result']);
        self::assertTrue(AjaxResponse::wrap(true)->toArray()['result']);
        self::assertNull(AjaxResponse::wrap(null)->toArray()['result']);
        self::assertSame([1, 2], AjaxResponse::wrap([1, 2])->toArray()['result']);

        $associative = AjaxResponse::wrap(['saved' => true])->toArray();
        self::assertTrue($associative['saved']);
        self::assertArrayNotHasKey('result', $associative);
    }
}
