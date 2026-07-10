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

use Hyperf\Validation\ValidationException;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Contracts\AjaxExceptionInterface;
use Zotenme\HyperfAjax\Contracts\ExceptionMapperInterface;

class ExceptionMapper implements ExceptionMapperInterface
{
    public function map(\Throwable $exception): AjaxResponse
    {
        if ($exception instanceof AjaxExceptionInterface) {
            return (new AjaxResponse())->error()->data($exception->toAjaxData());
        }

        if ($this->isValidationException($exception)) {
            return (new AjaxResponse())
                ->error('', 422)
                ->invalidFields($this->extractValidationErrors($exception));
        }

        if ($status = $this->extractStatusCode($exception)) {
            $message = $exception->getMessage() ?: $this->defaultStatusMessage($status);

            return $status >= 500
                ? (new AjaxResponse())->fatal($message, $status)
                : (new AjaxResponse())->error($message, $status);
        }

        if ($exception instanceof \Exception) {
            return (new AjaxResponse())->error($exception->getMessage());
        }

        return (new AjaxResponse())->fatal($exception->getMessage() ?: 'An error occurred');
    }

    protected function isValidationException(\Throwable $exception): bool
    {
        return (
            class_exists('Hyperf\Validation\ValidationException')
            && $exception instanceof ValidationException
        ) || method_exists($exception, 'errors')
            || method_exists($exception, 'getValidator')
            || property_exists($exception, 'validator');
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractValidationErrors(\Throwable $exception): array
    {
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

    protected function extractStatusCode(\Throwable $exception): ?int
    {
        if (method_exists($exception, 'getStatusCode')) {
            $status = (int) $exception->getStatusCode();
            return $status > 0 ? $status : null;
        }

        if (method_exists($exception, 'getCode')) {
            $code = (int) $exception->getCode();
            return $code >= 400 && $code <= 599 ? $code : null;
        }

        return null;
    }

    protected function defaultStatusMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'An error occurred',
        };
    }
}
