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

namespace Zotenme\HyperfAjax\Exception;

class ValidationException extends \RuntimeException
{
    /** @var array<string, mixed> */
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

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
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
