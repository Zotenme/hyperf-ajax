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

namespace Zotenme\HyperfAjax;

use Psr\Http\Message\ServerRequestInterface;
use Zotenme\HyperfAjax\Support\AjaxHandlerName;

class AjaxRequest
{
    public const HEADER_HANDLER = 'X-AJAX-HANDLER';

    public const HEADER_FLASH = 'X-AJAX-FLASH';

    public const HEADER_PARTIAL = 'X-AJAX-PARTIAL';

    public const HEADER_PARTIALS = 'X-AJAX-PARTIALS';

    public string $handler = '';

    public string $qualifiedHandler = '';

    public string $component = '';

    public bool $wantsFlash = false;

    public ?string $partial = null;

    /**
     * @var list<string>
     */
    public array $partialList = [];

    public ?ServerRequestInterface $request = null;

    public function fromRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;

        [$this->component, $this->handler] = $this->getAjaxHandlerName($request);
        $this->qualifiedHandler = trim($request->getHeaderLine(self::HEADER_HANDLER));
        $this->partial = $this->getAjaxPartialName($request);
        $this->partialList = $this->getAjaxHandlerPartialList($request);
        $this->wantsFlash = $request->getHeaderLine(self::HEADER_FLASH) !== '';

        return $this;
    }

    public function hasAjaxHandler(): bool
    {
        return $this->isAjaxRequest() && AjaxHandlerName::isValid($this->qualifiedHandler);
    }

    public function isAjaxRequest(): bool
    {
        if (! $this->request instanceof ServerRequestInterface) {
            return false;
        }

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return false;
        }

        if (strtolower($this->request->getHeaderLine('X-Requested-With')) !== 'xmlhttprequest') {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function getAjaxHandlerName(ServerRequestInterface $request): array
    {
        $handler = trim($request->getHeaderLine(self::HEADER_HANDLER));
        if ($handler === '') {
            return ['', ''];
        }

        if (str_contains($handler, '::')) {
            $parts = explode('::', $handler, 2);
            return [trim($parts[0]), trim($parts[1])];
        }

        return ['', $handler];
    }

    protected function getAjaxPartialName(ServerRequestInterface $request): ?string
    {
        $partial = trim($request->getHeaderLine(self::HEADER_PARTIAL));

        return $partial !== '' ? $partial : null;
    }

    /**
     * @return list<string>
     */
    protected function getAjaxHandlerPartialList(ServerRequestInterface $request): array
    {
        $partialList = trim($request->getHeaderLine(self::HEADER_PARTIALS));
        if ($partialList === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('&', $partialList))));
    }
}
