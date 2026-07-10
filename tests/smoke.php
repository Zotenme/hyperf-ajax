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
require __DIR__ . '/bootstrap.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Exception\ServerErrorHttpException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\AjaxResponse;
use Zotenme\HyperfAjax\Component\ViewComponentFactory;
use Zotenme\HyperfAjax\Concerns\ViewComponent;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;
use Zotenme\HyperfAjax\Exception\ValidationException as HyperfAjaxValidationException;
use Zotenme\HyperfAjax\Support\AjaxExecutionContext;
use Zotenme\HyperfAjax\Support\ExceptionMapper;
use Zotenme\HyperfAjax\Support\MethodInvoker;

class SmokeInjectedService
{
    public function value(): string
    {
        return 'injected';
    }
}

class SmokeContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $entries = [];

    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? $this->make($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) || class_exists($id);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function make(string $name, array $parameters = []): mixed
    {
        if (! class_exists($name)) {
            throw new RuntimeException("Class [{$name}] not found.");
        }

        /** @var class-string $name */
        $reflection = new ReflectionClass($name);
        $constructor = $reflection->getConstructor();
        if (! $constructor) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $parameters)) {
                $arguments[] = $parameters[$parameter->getName()];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
                continue;
            }

            $arguments[] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : null;
        }

        return $reflection->newInstanceArgs($arguments);
    }

    public function set(string $name, mixed $entry): void
    {
        $this->entries[$name] = $entry;
    }

    public function unbind(string $name): void
    {
        unset($this->entries[$name]);
    }

    /**
     * @param array<array-key, mixed>|callable|string $definition
     */
    public function define(string $name, mixed $definition): void
    {
        $this->entries[$name] = $definition;
    }
}

class SmokeViewComponent implements ViewComponentInterface
{
    use ViewComponent;

    public function __construct(
        public readonly SmokeInjectedService $service
    ) {}
}

class SmokeLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     * @param mixed $level
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$response = (new AjaxResponse())
    ->data(['answer' => 42])
    ->update(['#message' => 'Saved'])
    ->browserEvent('profile:saved', ['ok' => true])
    ->toArray();

assert_true($response['answer'] === 42, 'response data is merged at top level');
assert_true($response['__ajax']['ok'] === true, 'response is ok by default');
assert_true($response['__ajax']['ops'][0]['op'] === 'patchDom', 'patchDom op is emitted');
assert_true($response['__ajax']['ops'][0]['selector'] === '#message', 'selector is preserved');
assert_true($response['__ajax']['ops'][1]['op'] === 'dispatch', 'browser event op is emitted');

$shortcut = (new AjaxResponse())->dataWithUpdateSelectors([
    '@#list' => '<li>New</li>',
    'count' => 1,
])->toArray();

assert_true($shortcut['count'] === 1, 'non-selector data remains data');
assert_true($shortcut['__ajax']['ops'][0]['swap'] === 'append', 'append selector shortcut is supported');
assert_true($shortcut['__ajax']['ops'][0]['selector'] === '#list', 'selector modifier is stripped');

$logger = new SmokeLogger();
$unexpectedException = new class('Sensitive database connection details', 404) extends Exception {
    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return ['email' => ['Email is required']];
    }
};

$unexpectedError = (new ExceptionMapper($logger))->map($unexpectedException);

assert_true($unexpectedError->getStatusCode() === 500, 'duck-typed validation and arbitrary exception codes map to HTTP 500');
assert_true($unexpectedError->getMessage() === 'Internal Server Error', 'unexpected exception details are hidden by default');
assert_true($unexpectedError->getInvalidFields() === [], 'duck-typed errors are not treated as validation');
assert_true(count($logger->records) === 1, 'unexpected exceptions are logged');
assert_true($logger->records[0]['context']['exception'] === $unexpectedException, 'logger receives the original exception');

$notFoundError = (new ExceptionMapper($logger))->map(new NotFoundHttpException());

assert_true($notFoundError->getStatusCode() === 404, 'explicit Hyperf HTTP exceptions preserve their status');
assert_true($notFoundError->getMessage() === 'Not Found', 'explicit client HTTP exceptions preserve a safe message');
assert_true(count($logger->records) === 1, 'client HTTP exceptions are not logged as server failures');

$serverError = (new ExceptionMapper($logger))->map(new ServerErrorHttpException('Sensitive upstream details'));

assert_true($serverError->getStatusCode() === 500, 'server HTTP exceptions preserve their status');
assert_true($serverError->getMessage() === 'Internal Server Error', 'server HTTP exception details are hidden by default');
assert_true(count($logger->records) === 2, 'server HTTP exceptions are logged');

$debugError = (new ExceptionMapper($logger, true))->map(new Exception('Visible debug details'));
assert_true($debugError->getMessage() === 'Visible debug details', 'debug mapper can expose exception details explicitly');

$packageValidationException = new HyperfAjaxValidationException([
    'phone' => ['Phone is required'],
]);

$packageError = (new ExceptionMapper($logger))->map($packageValidationException);

assert_true($packageError->getStatusCode() === 422, 'package validation maps to HTTP 422');
assert_true($packageError->getInvalidFields()['phone'][0] === 'Phone is required', 'package validation errors are exposed');
assert_true($packageError->getMessage() === '', 'package validation does not trigger a generic alert message');

$validatorLike = new class {
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

$packageValidatorError = (new ExceptionMapper())->map(HyperfAjaxValidationException::fromValidator($validatorLike));

assert_true($packageValidatorError->getInvalidFields()['name'][0] === 'Name is required', 'package validation accepts validator-like objects');

$controller = new class {
    public function onSave(string $id = 'x'): string
    {
        return 'saved:' . $id;
    }
};

$service = new SmokeInjectedService();
$container = new SmokeContainer([
    SmokeInjectedService::class => $service,
    LoggerInterface::class => $logger,
    StdoutLoggerInterface::class => $logger,
]);
ApplicationContext::setContainer($container);

$result = (new MethodInvoker($container))->invoke([$controller, 'onSave'], ['id' => '42']);

assert_true($result === 'saved:42', 'method invoker resolves named parameters');

$diController = new class {
    public function onInjected(SmokeInjectedService $service): string
    {
        return $service->value();
    }
};

$diResult = (new MethodInvoker($container))->invoke([$diController, 'onInjected']);

assert_true($diResult === 'injected', 'method invoker resolves typed dependencies from container');

$contextController = new class extends HyperfAjaxController implements AjaxControllerInterface {
    public function activate(AjaxRequest $request): void
    {
        $this->setAjaxExecutionContext(new AjaxExecutionContext($request));
    }

    public function release(): void
    {
        $this->clearAjaxExecutionContext();
    }
};

$baseConstructor = new ReflectionMethod(HyperfAjaxController::class, '__construct');
assert_true($baseConstructor->getNumberOfRequiredParameters() === 0, 'base controller constructor is compatible with Hyperf DI proxies');

$component = (new ViewComponentFactory($container))->make(
    SmokeViewComponent::class,
    $contextController,
    ['alias' => 'injectedForm', 'enabled' => true]
);

assert_true($component instanceof SmokeViewComponent, 'component factory creates the requested component type');
if (! $component instanceof SmokeViewComponent) {
    throw new RuntimeException('Component factory returned an unexpected type.');
}

assert_true($component->service === $service, 'component constructor dependencies are resolved by the container');
assert_true($component->getController() === $contextController, 'component factory binds the controller');
assert_true($component->getAlias() === 'injectedForm', 'component factory applies configured alias');
assert_true($component->getConfig()['enabled'] === true, 'component factory applies component config');

$firstRequest = new AjaxRequest();
$firstRequest->handler = 'onFirst';
$secondRequest = new AjaxRequest();
$secondRequest->handler = 'onSecond';

Coroutine::setTestCid(1);
$contextController->activate($firstRequest);

Coroutine::setTestCid(2);
$contextController->activate($secondRequest);

assert_true($contextController->getAjaxRequest()?->handler === 'onSecond', 'current coroutine receives its own AJAX context');

Coroutine::setTestCid(1);
assert_true($contextController->getAjaxRequest()?->handler === 'onFirst', 'AJAX context is isolated between coroutines');
$contextController->release();

assert_true($contextController->getAjaxRequest() === null, 'AJAX context can be cleared in one coroutine');

Coroutine::setTestCid(2);
assert_true($contextController->getAjaxRequest()?->handler === 'onSecond', 'clearing one coroutine preserves another');
$contextController->release();
Coroutine::setTestCid(-1);

echo "Smoke tests passed.\n";
