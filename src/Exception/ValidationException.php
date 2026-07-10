<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Exception;

use RuntimeException;

class ValidationException extends RuntimeException
{
    protected array $errors = [];

    public function __construct(mixed $errors, string $message = '')
    {
        parent::__construct($message);

        $this->errors = $this->normalizeErrors($errors);
    }

    public static function fromValidator(object $validator, string $message = ''): self
    {
        return new self($validator, $message);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function normalizeErrors(mixed $errors): array
    {
        if (is_object($errors) && method_exists($errors, 'errors')) {
            return $this->normalizeErrors($errors->errors());
        }

        if (is_object($errors) && method_exists($errors, 'toArray')) {
            $array = $errors->toArray();
            return is_array($array) ? $array : [];
        }

        if (is_object($errors) && method_exists($errors, 'all')) {
            $all = $errors->all();
            return is_array($all) ? ['_error' => $all] : [];
        }

        return is_array($errors) ? $errors : [];
    }
}
