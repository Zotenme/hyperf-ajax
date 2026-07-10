<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax;

use Hyperf\HttpServer\Contract\ResponseInterface as HyperfResponseInterface;
use Zotenme\HyperfAjax\Contracts\AjaxExceptionInterface;
use Zotenme\HyperfAjax\Contracts\ExceptionMapperInterface;
use Zotenme\HyperfAjax\Support\AjaxHelpers;
use Zotenme\HyperfAjax\Support\ExceptionMapper;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use Throwable;

class AjaxResponse
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_FATAL = 'fatal';

    public const OP_FLASH = 'flash';
    public const OP_PATCH_DOM = 'patchDom';
    public const OP_PARTIAL = 'partial';
    public const OP_REDIRECT = 'redirect';
    public const OP_RELOAD = 'reload';
    public const OP_DISPATCH = 'dispatch';
    public const OP_LOAD_ASSETS = 'loadAssets';

    protected array $ajaxData = [
        'headers' => [
            'X-AJAX-RESPONSE' => '1',
        ],
        'status' => 200,
        'content' => [
            'ok' => true,
            'severity' => self::SEVERITY_INFO,
            'message' => null,
            'data' => [],
            'invalid' => [],
            'ops' => [],
            'redirect' => null,
        ],
    ];

    protected mixed $responseOverride = null;

    public static function wrap(mixed $result): static
    {
        if ($result instanceof self) {
            return $result;
        }

        $response = new self();

        if ($result instanceof ResponseInterface) {
            return $response->force($result);
        }

        if ($result instanceof JsonSerializable) {
            $json = $result->jsonSerialize();
            return is_array($json) && AjaxHelpers::isAssoc($json)
                ? $response->data($json)
                : $response->data(['result' => $json]);
        }

        if (is_array($result)) {
            return AjaxHelpers::isAssoc($result)
                ? $response->dataWithUpdateSelectors($result)
                : $response->data(['result' => $result]);
        }

        if ($result instanceof Stringable) {
            return $response->data(['result' => (string) $result]);
        }

        if (is_string($result) || is_numeric($result) || is_bool($result) || $result === null) {
            return $response->data(['result' => $result]);
        }

        return $response->force($result);
    }

    public function toArray(): array
    {
        $env = $this->ajaxData['content'];
        $data = $env['data'];
        unset($env['data']);

        return [
            ...$data,
            '__ajax' => $env,
        ];
    }

    public function toPsrResponse(HyperfResponseInterface $response): ResponseInterface
    {
        if ($this->responseOverride instanceof ResponseInterface) {
            return $this->responseOverride;
        }

        $psrResponse = $response->json($this->toArray())
            ->withStatus($this->getStatusCode());

        foreach ($this->getHeaders() as $name => $value) {
            $psrResponse = $psrResponse->withHeader($name, (string) $value);
        }

        return $psrResponse;
    }

    public function update(array $updates): static
    {
        foreach ($updates as $target => $update) {
            if (! is_array($update)) {
                $update = ['content' => $update];
            }

            $update['target'] ??= $target;
            $update['content'] = $this->normalizeRenderable($update['content'] ?? '');

            $this->ajaxData['content']['ops'][] = [
                'op' => self::OP_PATCH_DOM,
                'selector' => $update['target'],
                'html' => $update['content'],
                'swap' => $update['swap'] ?? 'update',
            ];
        }

        return $this;
    }

    public function data(array $data): static
    {
        $this->ajaxData['content']['data'] = array_replace(
            $this->ajaxData['content']['data'] ?? [],
            $data
        );

        return $this;
    }

    public function redirect(string $location): static
    {
        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_REDIRECT,
            'url' => $location,
        ];

        $this->ajaxData['content']['redirect'] = $location;

        return $this;
    }

    public function reload(): static
    {
        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_RELOAD,
        ];

        return $this;
    }

    public function flash(string $level, string $text): static
    {
        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_FLASH,
            'level' => $level,
            'text' => $text,
        ];

        return $this;
    }

    public function error(string $message = '', int $status = 400): static
    {
        $this->ajaxData['content']['ok'] = false;
        $this->ajaxData['content']['severity'] = self::SEVERITY_ERROR;
        $this->ajaxData['status'] = $status;
        $this->ajaxData['content']['message'] = $message;

        return $this;
    }

    public function fatal(string $message, int $status = 500): static
    {
        $this->ajaxData['content']['ok'] = false;
        $this->ajaxData['content']['severity'] = self::SEVERITY_FATAL;
        $this->ajaxData['status'] = $status;
        $this->ajaxData['content']['message'] = $message;

        return $this;
    }

    public function js(string|array $paths, array $attributes = []): static
    {
        return $this->asset('js', $paths, $attributes);
    }

    public function css(string|array $paths, array $attributes = []): static
    {
        return $this->asset('css', $paths, $attributes);
    }

    public function img(string|array $paths, array $attributes = []): static
    {
        return $this->asset('img', $paths, $attributes);
    }

    public function jsInline(string $code, array $attributes = []): static
    {
        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_LOAD_ASSETS,
            'type' => 'js',
            'assets' => [
                ['inline' => $code, 'attributes' => $this->normalizeAssetAttributes($attributes)],
            ],
        ];

        return $this;
    }

    public function asset(string $type, string|array $paths, array $attributes = []): static
    {
        if ($paths === '' || $paths === []) {
            return $this;
        }

        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_LOAD_ASSETS,
            'type' => $type,
            'assets' => $this->normalizeAssetPaths($paths, $attributes),
        ];

        return $this;
    }

    public function browserEvent(string $name, mixed $data = null): static
    {
        return $this->browserEventInternal($name, $data, false);
    }

    public function browserEventAsync(string $name, mixed $data = null): static
    {
        return $this->browserEventInternal($name, $data, true);
    }

    public function invalidFields(array $errors): static
    {
        $this->ajaxData['status'] = 422;
        $this->ajaxData['content']['ok'] = false;
        $this->ajaxData['content']['severity'] = self::SEVERITY_ERROR;

        $invalid = (array) ($this->ajaxData['content']['invalid'] ?? []);
        foreach ($errors as $field => $messages) {
            $invalid[$field] = array_values((array) $messages);
        }

        $this->ajaxData['content']['invalid'] = $invalid;

        return $this;
    }

    public function invalidField(string $field, string|array $messages): static
    {
        return $this->invalidFields([$field => $messages]);
    }

    public function partials(array $partials): static
    {
        foreach ($partials as $name => $content) {
            $this->partial((string) $name, $content);
        }

        return $this;
    }

    public function partial(string $name, mixed $content): static
    {
        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_PARTIAL,
            'name' => $name,
            'html' => $this->normalizeRenderable($content),
        ];

        return $this;
    }

    public function exception(Throwable $exception, ?ExceptionMapperInterface $mapper = null): static
    {
        return ($mapper ?? new ExceptionMapper())->map($exception);
    }

    public function force(mixed $response): static
    {
        $this->responseOverride = $response;

        return $this;
    }

    public function headers(array $headers): static
    {
        $this->ajaxData['headers'] = [
            ...$headers,
            ...($this->ajaxData['headers'] ?? []),
        ];

        return $this;
    }

    public function dataWithUpdateSelectors(array $dataAndUpdates): static
    {
        $data = $dataAndUpdates;
        $updates = [];
        $selectors = ['#', '.', '@', '^', '!', '='];
        $modifiers = [
            '@' => 'append',
            '^' => 'prepend',
            '!' => 'replace',
            '=' => 'update',
        ];

        foreach ($data as $target => $content) {
            foreach ($selectors as $selector) {
                if (is_string($target) && str_starts_with($target, $selector)) {
                    unset($data[$target]);

                    if (isset($modifiers[$selector])) {
                        $target = substr($target, 1);
                    }

                    $updates[] = [
                        'target' => $target,
                        'content' => $content,
                        'swap' => $modifiers[$selector] ?? 'update',
                    ];
                }
            }
        }

        return $this->data($data)->update($updates);
    }

    public function isOk(): bool
    {
        return $this->ajaxData['content']['ok'] === true;
    }

    public function isError(): bool
    {
        return in_array($this->ajaxData['content']['severity'], [
            self::SEVERITY_ERROR,
            self::SEVERITY_FATAL,
        ], true);
    }

    public function isFatal(): bool
    {
        return $this->ajaxData['content']['severity'] === self::SEVERITY_FATAL;
    }

    public function isRedirect(): bool
    {
        return $this->ajaxData['content']['redirect'] !== null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->ajaxData['content']['redirect'];
    }

    public function getData(): array
    {
        return $this->ajaxData['content']['data'] ?? [];
    }

    public function getMessage(): ?string
    {
        return $this->ajaxData['content']['message'];
    }

    public function getSeverity(): string
    {
        return $this->ajaxData['content']['severity'];
    }

    public function getStatusCode(): int
    {
        return $this->ajaxData['status'];
    }

    public function getHeaders(): array
    {
        return $this->ajaxData['headers'] ?? [];
    }

    public function getOps(): array
    {
        return $this->ajaxData['content']['ops'] ?? [];
    }

    public function getInvalidFields(): array
    {
        return $this->ajaxData['content']['invalid'] ?? [];
    }

    public function isForced(): bool
    {
        return $this->responseOverride !== null;
    }

    protected function browserEventInternal(string $name, mixed $data, bool $isAsync): static
    {
        $this->ajaxData['content']['ops'][] = [
            'op' => self::OP_DISPATCH,
            'event' => $name,
            'detail' => $data,
            'async' => $isAsync,
        ];

        return $this;
    }

    protected function normalizeAssetPaths(string|array $paths, array $attributes = []): array
    {
        $attributes = $this->normalizeAssetAttributes($attributes);

        if (is_string($paths)) {
            return $attributes ? [['url' => $paths, 'attributes' => $attributes]] : [$paths];
        }

        $assets = [];
        foreach ($paths as $key => $value) {
            if (is_string($key)) {
                $attrs = is_array($value) ? $this->normalizeAssetAttributes($value) : [];
                $assets[] = $attrs ? ['url' => $key, 'attributes' => $attrs] : $key;
                continue;
            }

            $assets[] = $attributes ? ['url' => $value, 'attributes' => $attributes] : $value;
        }

        return $assets;
    }

    protected function normalizeAssetAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $normalized[$value] = true;
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    protected function normalizeRenderable(mixed $content): string
    {
        if ($content instanceof Stringable) {
            return (string) $content;
        }

        if (is_scalar($content)) {
            return (string) $content;
        }

        if ($content === null) {
            return '';
        }

        return '<!-- Unknown Type: ' . gettype($content) . ' -->';
    }
}
