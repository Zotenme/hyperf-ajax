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

namespace Zotenme\HyperfAjax\Concerns;

use Hyperf\Context\Context;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HyperfResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Component\ComponentContainer;
use Zotenme\HyperfAjax\Component\ViewComponentFactory;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Contracts\AjaxHandlerInvokerInterface;
use Zotenme\HyperfAjax\Contracts\ExceptionMapperInterface;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;
use Zotenme\HyperfAjax\Exception\ComponentNotFound;
use Zotenme\HyperfAjax\Exception\HandlerNameInvalid;
use Zotenme\HyperfAjax\Exception\HandlerNotFound;
use Zotenme\HyperfAjax\Support\AjaxExecutionContext;
use Zotenme\HyperfAjax\Support\AjaxHandlerName;
use Zotenme\HyperfAjax\Support\AjaxHelpers;
use Zotenme\HyperfAjax\Support\ExceptionMapper;
use Zotenme\HyperfAjax\Support\MethodInvoker;

trait InteractsWithAjax
{
    public function __get(string $name): mixed
    {
        if ($name === 'ajax') {
            return $this->ajax();
        }

        trigger_error('Undefined property: ' . static::class . '::$' . $name, E_USER_NOTICE);

        return null;
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    public function handleAjax(
        ServerRequestInterface $request,
        HyperfResponseInterface $response,
        string $action = '',
        array $parameters = []
    ): ?ResponseInterface {
        $ajaxRequest = (new AjaxRequest())->fromRequest($request);

        if (! $ajaxRequest->isAjaxRequest()) {
            return null;
        }

        $previousContext = $this->getAjaxExecutionContext();
        $this->setAjaxExecutionContext(new AjaxExecutionContext($ajaxRequest));

        try {
            $this->initAjaxComponents();

            return $this->runAjaxAction($action, $parameters)->toPsrResponse($response);
        } catch (\Throwable $exception) {
            return $this->ajax()->exception($exception, $this->getAjaxExceptionMapper())->toPsrResponse($response);
        } finally {
            if ($previousContext instanceof AjaxExecutionContext) {
                $this->setAjaxExecutionContext($previousContext);
            } else {
                $this->clearAjaxExecutionContext();
            }
        }
    }

    public function ajax(): AjaxResponse
    {
        return new AjaxResponse();
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
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
        return $this->getAjaxExecutionContext()?->request;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function ajaxAll(): array
    {
        $request = $this->getAjaxRequest()?->request;
        if (! $request instanceof ServerRequestInterface) {
            return [];
        }

        $body = $request->getParsedBody();
        $query = $request->getQueryParams();

        return [
            ...$query,
            ...(is_array($body) ? $body : []),
        ];
    }

    public function ajaxPost(?string $key = null, mixed $default = null): mixed
    {
        $request = $this->getAjaxRequest()?->request;
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

    /**
     * @param array<string, mixed> $data
     */
    public function withAjaxPartialData(array $data): static
    {
        $context = $this->getAjaxExecutionContext();
        if (! $context instanceof AjaxExecutionContext) {
            throw new \RuntimeException('AJAX partial data can only be set during an active AJAX request.');
        }

        $context->partialData = array_replace($context->partialData, $data);

        return $this;
    }

    public function addComponentInstance(string $alias, ViewComponentInterface $instance): void
    {
        if (! $this instanceof AjaxControllerInterface) {
            throw new \RuntimeException('Controllers using AJAX components must implement AjaxControllerInterface.');
        }

        if (! $instance->getController()) {
            $instance->setController($this);
        }

        if ($instance->getAlias() === '') {
            $instance->setAlias($alias);
        }

        $context = $this->getAjaxExecutionContext();
        if (! $context instanceof AjaxExecutionContext) {
            throw new \RuntimeException('AJAX components can only be registered during an active AJAX request.');
        }

        $context->components ??= $this->makeAjaxComponentContainer();
        $context->components->bind($alias, $instance);
    }

    public function getComponentInstance(string $alias): ?ViewComponentInterface
    {
        $component = $this->getAjaxExecutionContext()?->components?->make($alias);

        return $component instanceof ViewComponentInterface ? $component : null;
    }

    abstract protected function getAjaxContainer(): ContainerInterface;

    /**
     * @param array<array-key, mixed> $parameters
     */
    protected function runAjaxAction(string $action, array $parameters): AjaxResponse
    {
        $ajaxRequest = $this->getAjaxRequest();
        $handler = $ajaxRequest?->handler ?? '';
        $qualifiedHandler = $ajaxRequest?->qualifiedHandler ?? '';
        if ($ajaxRequest instanceof AjaxRequest && $qualifiedHandler === '' && $handler !== '') {
            $qualifiedHandler = $ajaxRequest->component !== ''
                ? $ajaxRequest->component . '::' . $handler
                : $handler;
        }
        if (! AjaxHandlerName::isValid($qualifiedHandler)) {
            throw new HandlerNameInvalid("[{$qualifiedHandler}] is an invalid AJAX handler name");
        }

        $method = $this->getAjaxHandlerMethod($action);
        if ($method === null) {
            throw new HandlerNotFound("AJAX handler [{$handler}] not found");
        }

        if (! is_callable($method)) {
            throw new HandlerNotFound("AJAX handler [{$handler}] is not callable");
        }

        $result = $this->getAjaxHandlerInvoker()->invoke($method, $parameters);

        $response = AjaxResponse::wrap($result);

        $this->renderRequestedAjaxPartials($response);

        return $response;
    }

    protected function getAjaxHandlerInvoker(): AjaxHandlerInvokerInterface
    {
        $container = $this->getAjaxContainer();
        if ($container->has(AjaxHandlerInvokerInterface::class)) {
            $invoker = $container->get(AjaxHandlerInvokerInterface::class);
            if ($invoker instanceof AjaxHandlerInvokerInterface) {
                return $invoker;
            }
        }

        return new MethodInvoker($container);
    }

    protected function renderRequestedAjaxPartials(AjaxResponse $response): void
    {
        $context = $this->getAjaxExecutionContext();
        if (! $context instanceof AjaxExecutionContext || $context->request->partialList === []) {
            return;
        }

        $renderer = $this->getAjaxPartialRenderer();
        if (! $renderer instanceof PartialRendererInterface) {
            return;
        }

        foreach ($context->request->partialList as $partial) {
            $response->partial($partial, $renderer->render(
                $partial,
                $this,
                $context->request,
                $context->partialData
            ));
        }
    }

    protected function getAjaxPartialRenderer(): ?PartialRendererInterface
    {
        $container = $this->getAjaxContainer();
        if (! $container->has(PartialRendererInterface::class)) {
            return null;
        }

        $renderer = $container->get(PartialRendererInterface::class);

        return $renderer instanceof PartialRendererInterface ? $renderer : null;
    }

    /**
     * @return null|array{0: object, 1: string}
     */
    protected function getAjaxHandlerMethod(string $action): ?array
    {
        $ajaxRequest = $this->getAjaxRequest();
        $handler = $ajaxRequest?->handler ?? '';
        if ($handler === '') {
            return null;
        }

        if (($component = $ajaxRequest?->component) !== null && $component !== '') {
            $componentObject = $this->getAjaxExecutionContext()?->components?->make($component);
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

        return $this->getAjaxExecutionContext()?->components?->getAjaxHandlerMethod($handler);
    }

    protected function initAjaxComponents(): void
    {
        if (! $this instanceof AjaxControllerInterface) {
            return;
        }

        $context = $this->getAjaxExecutionContext();
        if (! $context instanceof AjaxExecutionContext) {
            return;
        }

        $context->components = $this->makeAjaxComponentContainer();
        $context->components->register();
        $context->components->boot();
    }

    protected function makeAjaxComponentContainer(): ComponentContainer
    {
        return new ComponentContainer(
            $this,
            new ViewComponentFactory($this->getAjaxContainer())
        );
    }

    protected function getAjaxExecutionContext(): ?AjaxExecutionContext
    {
        $context = Context::get($this->getAjaxExecutionContextKey());

        return $context instanceof AjaxExecutionContext ? $context : null;
    }

    protected function setAjaxExecutionContext(AjaxExecutionContext $context): void
    {
        Context::set($this->getAjaxExecutionContextKey(), $context);
    }

    protected function clearAjaxExecutionContext(): void
    {
        Context::destroy($this->getAjaxExecutionContextKey());
    }

    protected function getAjaxExecutionContextKey(): string
    {
        return 'hyperf-ajax.execution.' . static::class . '.' . spl_object_id($this);
    }

    protected function getAjaxExceptionMapper(): ExceptionMapperInterface
    {
        $container = $this->getAjaxContainer();
        if ($container->has(ExceptionMapperInterface::class)) {
            $mapper = $container->get(ExceptionMapperInterface::class);
            if ($mapper instanceof ExceptionMapperInterface) {
                return $mapper;
            }
        }

        $logger = null;
        foreach ([LoggerInterface::class, StdoutLoggerInterface::class] as $loggerClass) {
            if (! $container->has($loggerClass)) {
                continue;
            }

            $candidate = $container->get($loggerClass);
            if ($candidate instanceof LoggerInterface) {
                $logger = $candidate;
                break;
            }
        }

        return new ExceptionMapper($logger);
    }
}
