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

use Hyperf\HttpMessage\Server\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Support\AjaxHandlerName;

/**
 * @internal
 * @coversNothing
 */
class AjaxRequestTest extends TestCase
{
    #[DataProvider('validHandlerProvider')]
    public function testAcceptsProtocolHandlerNames(string $handler): void
    {
        $request = $this->ajaxRequest($handler);

        self::assertTrue(AjaxHandlerName::isValid($handler));
        self::assertTrue($request->hasAjaxHandler());
    }

    /** @return iterable<string, array{string}> */
    public static function validHandlerProvider(): iterable
    {
        yield 'controller' => ['onSave'];
        yield 'digits and underscore' => ['onSave_2'];
        yield 'component' => ['ProfileForm::onSave'];
        yield 'component alias starting with digit' => ['2fa::onVerify'];
    }

    #[DataProvider('invalidHandlerProvider')]
    public function testRejectsProtocolHandlerNames(string $handler): void
    {
        $request = $this->ajaxRequest($handler);

        self::assertFalse(AjaxHandlerName::isValid($handler));
        self::assertFalse($request->hasAjaxHandler());
        self::assertTrue($request->isAjaxRequest());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidHandlerProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'missing on prefix' => ['save'];
        yield 'lowercase event' => ['onsave'];
        yield 'plus accepted by upstream DOM regex' => ['onSave+'];
        yield 'empty component' => ['::onSave'];
        yield 'multiple separators' => ['Form::Nested::onSave'];
        yield 'method injection' => ['onSave()'];
        yield 'embedded newline' => ["onSave\nInjected"];
    }

    public function testKeepsCurrentPartialContextSeparateFromResolvedRenderList(): void
    {
        $request = $this->ajaxRequest('onRefresh', [
            AjaxRequest::HEADER_PARTIAL => 'profile/card',
            AjaxRequest::HEADER_PARTIALS => 'profile/card&sidebar',
        ]);

        self::assertSame('profile/card', $request->partial);
        self::assertSame(['profile/card', 'sidebar'], $request->partialList);
    }

    public function testCurrentPartialAloneDoesNotRequestBackendRendering(): void
    {
        $request = $this->ajaxRequest('onRefresh', [
            AjaxRequest::HEADER_PARTIAL => 'profile/card',
        ]);

        self::assertSame('profile/card', $request->partial);
        self::assertSame([], $request->partialList);
    }

    /** @param array<string, string> $headers */
    private function ajaxRequest(string $handler, array $headers = []): AjaxRequest
    {
        $request = new Request('POST', '/', [
            'X-Requested-With' => 'XMLHttpRequest',
            AjaxRequest::HEADER_HANDLER => $handler,
            ...$headers,
        ]);

        return (new AjaxRequest())->fromRequest($request);
    }
}
