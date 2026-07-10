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

use Psr\Container\ContainerInterface;
use Swoole\Coroutine;
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\AjaxResponse;
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

$validationException = new class('Invalid data') extends Exception {
    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return ['email' => ['Email is required']];
    }
};

$error = (new ExceptionMapper())->map($validationException);

assert_true($error->getStatusCode() === 422, 'validation maps to HTTP 422');
assert_true($error->getInvalidFields()['email'][0] === 'Email is required', 'validation fields are exposed');
assert_true($error->getMessage() === '', 'validation does not trigger a generic alert message');

$hyperfValidationException = new class('The given data was invalid.') extends Exception {
    public object $validator;

    public function __construct(string $message)
    {
        parent::__construct($message);

        $this->validator = new class {
            public function errors(): object
            {
                return new class {
                    /** @return array<string, list<string>> */
                    public function toArray(): array
                    {
                        return ['email' => ['Email is required']];
                    }
                };
            }
        };
    }
};

$hyperfError = (new ExceptionMapper())->map($hyperfValidationException);

assert_true($hyperfError->getStatusCode() === 422, 'hyperf validation maps to HTTP 422');
assert_true($hyperfError->getInvalidFields()['email'][0] === 'Email is required', 'hyperf validator errors are exposed');
assert_true($hyperfError->getMessage() === '', 'hyperf validation does not trigger a generic alert message');

$packageValidationException = new HyperfAjaxValidationException([
    'phone' => ['Phone is required'],
]);

$packageError = (new ExceptionMapper())->map($packageValidationException);

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

$result = (new MethodInvoker())->invoke([$controller, 'onSave'], ['id' => '42']);

assert_true($result === 'saved:42', 'method invoker resolves named parameters');

$service = new SmokeInjectedService();

$container = new class($service) implements ContainerInterface {
    public function __construct(private object $service) {}

    public function get(string $id): object
    {
        return $this->service;
    }

    public function has(string $id): bool
    {
        return $id === $this->service::class;
    }
};

$diController = new class {
    public function onInjected(SmokeInjectedService $service): string
    {
        return $service->value();
    }
};

$diResult = (new MethodInvoker($container))->invoke([$diController, 'onInjected']);

assert_true($diResult === 'injected', 'method invoker resolves typed dependencies from container');

$contextController = new class extends HyperfAjaxController {
    public function activate(AjaxRequest $request): void
    {
        $this->setAjaxExecutionContext(new AjaxExecutionContext($request));
    }

    public function release(): void
    {
        $this->clearAjaxExecutionContext();
    }
};

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
