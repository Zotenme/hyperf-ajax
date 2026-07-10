<?php

declare(strict_types=1);

namespace Zotenme\HyperfAjax\Concerns;

use Hyperf\HttpServer\Contract\ResponseInterface as HyperfResponseInterface;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Component\ComponentContainer;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Contracts\ExceptionMapperInterface;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;
use Zotenme\HyperfAjax\Exception\ComponentNotFound;
use Zotenme\HyperfAjax\Exception\HandlerNameInvalid;
use Zotenme\HyperfAjax\Exception\HandlerNotFound;
use Zotenme\HyperfAjax\Support\AjaxHelpers;
use Zotenme\HyperfAjax\Support\ExceptionMapper;
use Zotenme\HyperfAjax\Support\MethodInvoker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

trait InteractsWithAjax
{
    protected ?AjaxRequest $ajaxRequest = null;

    protected ?ComponentContainer $componentContainer = null;

    public function handleAjax(
        ServerRequestInterface $request,
        HyperfResponseInterface $response,
        string $action = '',
        array $parameters = []
    ): ?ResponseInterface {
        $this->ajaxRequest = (new AjaxRequest())->fromRequest($request);

        if (! $this->ajaxRequest->hasAjaxHandler()) {
            return null;
        }

        try {
            $this->initAjaxComponents();

            return $this->runAjaxAction($action, $parameters)->toPsrResponse($response);
        } catch (Throwable $exception) {
            return $this->ajax()->exception($exception, $this->getAjaxExceptionMapper())->toPsrResponse($response);
        }
    }

    public function ajax(): AjaxResponse
    {
        return new AjaxResponse();
    }

    public function __get(string $name): mixed
    {
        if ($name === 'ajax') {
            return $this->ajax();
        }

        trigger_error('Undefined property: ' . static::class . '::$' . $name, E_USER_NOTICE);

        return null;
    }

    public function ajaxPage(
        ServerRequestInterface $request,
        HyperfResponseInterface $response,
        callable $render,
        string $action = '',
        array $parameters = []
    ): mixed {
        if ($ajax = $this->handleAjax($request, $response, $action, $parameters)) {
            return $ajax;
        }

        return $render();
    }

    public function getAjaxRequest(): ?AjaxRequest
    {
        return $this->ajaxRequest;
    }

    public function ajaxAll(): array
    {
        $request = $this->ajaxRequest?->request;
        if (! $request instanceof ServerRequestInterface) {
            return [];
        }

        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        return [
            ...(is_array($query) ? $query : []),
            ...(is_array($body) ? $body : []),
        ];
    }

    public function ajaxPost(?string $key = null, mixed $default = null): mixed
    {
        $request = $this->ajaxRequest?->request;
        if (! $request instanceof ServerRequestInterface) {
            return $key === null ? [] : $default;
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        if ($key === null) {
            return $body;
        }

        return $body[$key] ?? $default;
    }

    public function ajaxInput(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->ajaxAll();

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    public function addComponentInstance(string $alias, ViewComponentInterface $instance): void
    {
        if (! $this instanceof AjaxControllerInterface) {
            throw new RuntimeException('Controllers using AJAX components must implement AjaxControllerInterface.');
        }

        if (! $instance->controller) {
            $instance->controller = $this;
        }

        if (! $instance->alias) {
            $instance->alias = $alias;
        }

        $this->componentContainer ??= new ComponentContainer($this);
        $this->componentContainer->bind($alias, $instance);
    }

    public function getComponentInstance(string $alias): ?ViewComponentInterface
    {
        $component = $this->componentContainer?->make($alias);

        return $component instanceof ViewComponentInterface ? $component : null;
    }

    protected function runAjaxAction(string $action, array $parameters): AjaxResponse
    {
        $handler = $this->ajaxRequest?->handler ?? '';
        if ($handler === '') {
            throw new HandlerNotFound('AJAX handler not specified');
        }

        if (! preg_match('/^on[A-Z][a-zA-Z0-9_]*$/', $handler)) {
            throw new HandlerNameInvalid("[{$handler}] is an invalid AJAX handler name");
        }

        $method = $this->getAjaxHandlerMethod($action);
        if ($method === null) {
            throw new HandlerNotFound("AJAX handler [{$handler}] not found");
        }

        if (method_exists($this, 'makeCallForAjax')) {
            $result = $this->makeCallForAjax($method, $parameters);
        } else {
            $result = (new MethodInvoker($this->getAjaxContainer()))->invoke($method, $parameters);
        }

        $response = AjaxResponse::wrap($result);

        if ($this->ajaxRequest?->partialList && method_exists($this, 'makePartialForAjax')) {
            foreach ($this->ajaxRequest->partialList as $partial) {
                $response->partial($partial, $this->makePartialForAjax($partial));
            }
        }

        return $response;
    }

    /**
     * @return array{0: object, 1: string}|null
     */
    protected function getAjaxHandlerMethod(string $action): ?array
    {
        $handler = $this->ajaxRequest?->handler ?? '';
        if ($handler === '') {
            return null;
        }

        if (($component = $this->ajaxRequest?->component) !== null && $component !== '') {
            $componentObject = $this->componentContainer?->make($component);
            if ($componentObject) {
                return [$componentObject, $handler];
            }

            throw new ComponentNotFound("Component name [{$component}] not found");
        }

        if ($action !== '') {
            $actionHandler = $action . '_' . $handler;
            if (AjaxHelpers::methodExists($this, $actionHandler)) {
                return [$this, $actionHandler];
            }
        }

        if (AjaxHelpers::methodExists($this, $handler)) {
            return [$this, $handler];
        }

        return $this->componentContainer?->getAjaxHandlerMethod($handler);
    }

    protected function initAjaxComponents(): void
    {
        if (! $this instanceof AjaxControllerInterface) {
            return;
        }

        $this->componentContainer = new ComponentContainer($this);
        $this->componentContainer->register();
        $this->componentContainer->boot();
    }

    protected function getAjaxContainer(): mixed
    {
        if (property_exists($this, 'container')) {
            return $this->container;
        }

        if (method_exists($this, 'getContainer')) {
            return $this->getContainer();
        }

        return null;
    }

    protected function getAjaxExceptionMapper(): ExceptionMapperInterface
    {
        if (property_exists($this, 'ajaxExceptionMapper') && $this->ajaxExceptionMapper instanceof ExceptionMapperInterface) {
            return $this->ajaxExceptionMapper;
        }

        if (method_exists($this, 'makeAjaxExceptionMapper')) {
            $mapper = $this->makeAjaxExceptionMapper();
            if ($mapper instanceof ExceptionMapperInterface) {
                return $mapper;
            }
        }

        return new ExceptionMapper();
    }
}
