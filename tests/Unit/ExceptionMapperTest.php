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

use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Exception\ServerErrorHttpException;
use PHPUnit\Framework\TestCase;
use Zotenme\HyperfAjax\Exception\ValidationException;
use Zotenme\HyperfAjax\Support\ExceptionMapper;
use Zotenme\HyperfAjax\Tests\Support\TestLogger;

/**
 * @internal
 * @coversNothing
 */
class ExceptionMapperTest extends TestCase
{
    public function testMapsOnlyExplicitValidationExceptions(): void
    {
        $logger = new TestLogger();
        $exception = new class('Sensitive database connection details', 404) extends \Exception {
            /** @return array<string, list<string>> */
            public function errors(): array
            {
                return ['email' => ['Email is required']];
            }
        };

        $response = (new ExceptionMapper($logger))->map($exception);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getMessage());
        self::assertSame([], $response->getInvalidFields());
        self::assertCount(1, $logger->records);
        self::assertSame($exception, $logger->records[0]['context']['exception']);
    }

    public function testMapsHttpExceptionsSafely(): void
    {
        $logger = new TestLogger();
        $mapper = new ExceptionMapper($logger);

        $notFound = $mapper->map(new NotFoundHttpException());
        self::assertSame(404, $notFound->getStatusCode());
        self::assertSame('Not Found', $notFound->getMessage());
        self::assertCount(0, $logger->records);

        $serverError = $mapper->map(new ServerErrorHttpException('Sensitive upstream details'));
        self::assertSame(500, $serverError->getStatusCode());
        self::assertSame('Internal Server Error', $serverError->getMessage());
        self::assertCount(1, $logger->records);
    }

    public function testCanExposeErrorsOnlyWithExplicitDebugMapper(): void
    {
        $response = (new ExceptionMapper(new TestLogger(), true))
            ->map(new \Exception('Visible debug details'));

        self::assertSame('Visible debug details', $response->getMessage());
    }

    public function testMapsPackageValidationException(): void
    {
        $exception = new ValidationException([
            'phone' => ['Phone is required'],
        ]);

        $response = (new ExceptionMapper(new TestLogger()))->map($exception);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Phone is required', $response->getInvalidFields()['phone'][0]);
        self::assertSame('', $response->getMessage());
    }

    public function testAcceptsValidatorLikeObjectInPackageException(): void
    {
        $validator = new class {
            public function errors(): object
            {
                return new class {
                    /** @return array<string, list<string>> */
                    public function toArray(): array
                    {
                        return ['name' => ['Name is required']];
                    }
                };
            }
        };

        $response = (new ExceptionMapper(new TestLogger()))
            ->map(ValidationException::fromValidator($validator));

        self::assertSame('Name is required', $response->getInvalidFields()['name'][0]);
    }
}
