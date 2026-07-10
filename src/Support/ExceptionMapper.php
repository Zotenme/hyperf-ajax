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

namespace Zotenme\HyperfAjax\Support;

use Hyperf\HttpMessage\Exception\HttpException;
use Psr\Log\LoggerInterface;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Contracts\AjaxExceptionInterface;
use Zotenme\HyperfAjax\Contracts\ExceptionMapperInterface;
use Zotenme\HyperfAjax\Exception\ValidationException;

class ExceptionMapper implements ExceptionMapperInterface
{
    public function __construct(
        protected ?LoggerInterface $logger = null,
        protected bool $debug = false
    ) {}

    public function map(\Throwable $exception): AjaxResponse
    {
        if ($exception instanceof AjaxExceptionInterface) {
            return (new AjaxResponse())->error()->data($exception->toAjaxData());
        }

        if ($exception instanceof ValidationException || $this->isHyperfValidationException($exception)) {
            return (new AjaxResponse())
                ->error('', 422)
                ->invalidFields($this->extractValidationErrors($exception));
        }

        if ($exception instanceof HttpException) {
            return $this->mapHttpException($exception);
        }

        $this->report($exception);

        return (new AjaxResponse())->fatal(
            $this->debug && $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'Internal Server Error'
        );
    }

    protected function isHyperfValidationException(\Throwable $exception): bool
    {
        $class = 'Hyperf\Validation\ValidationException';

        return class_exists($class) && is_a($exception, $class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractValidationErrors(\Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return $exception->errors();
        }

        if (method_exists($exception, 'errors')) {
            return $this->normalizeValidationErrors($exception->errors());
        }

        if (method_exists($exception, 'getValidator')) {
            $validator = $exception->getValidator();
            if (is_object($validator) && method_exists($validator, 'errors')) {
                return $this->normalizeValidationErrors($validator->errors());
            }
        }

        if (property_exists($exception, 'validator')) {
            $validator = $exception->validator;
            if (is_object($validator) && method_exists($validator, 'errors')) {
                return $this->normalizeValidationErrors($validator->errors());
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeValidationErrors(mixed $errors): array
    {
        if (is_array($errors)) {
            return $errors;
        }

        if (is_object($errors) && method_exists($errors, 'toArray')) {
            $array = $errors->toArray();
            return is_array($array) ? $array : [];
        }

        if (is_object($errors) && method_exists($errors, 'all')) {
            $all = $errors->all();
            return is_array($all) ? ['_error' => $all] : [];
        }

        return [];
    }

    protected function mapHttpException(HttpException $exception): AjaxResponse
    {
        $status = $exception->getStatusCode();
        if ($status < 400 || $status > 599) {
            $this->report($exception);

            return (new AjaxResponse())->fatal('Internal Server Error');
        }

        if ($status >= 500) {
            $this->report($exception);
        }

        $message = $status >= 500 && ! $this->debug
            ? $exception->getName()
            : ($exception->getMessage() ?: $exception->getName());

        return $status >= 500
            ? (new AjaxResponse())->fatal($message, $status)
            : (new AjaxResponse())->error($message, $status);
    }

    protected function report(\Throwable $exception): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error('Unhandled exception during an AJAX request.', [
                'exception' => $exception,
            ]);
            return;
        }

        error_log('[Hyperf Ajax] ' . (string) $exception);
    }
}
