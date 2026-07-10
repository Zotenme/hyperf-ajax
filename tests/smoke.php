<?php

declare(strict_types=1);

require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../src/Contracts/AjaxExceptionInterface.php';
require __DIR__ . '/../src/Contracts/ExceptionMapperInterface.php';
require __DIR__ . '/../src/Support/AjaxHelpers.php';
require __DIR__ . '/../src/Support/ExceptionMapper.php';
require __DIR__ . '/../src/Support/MethodInvoker.php';
require __DIR__ . '/../src/Exception/ValidationException.php';
require __DIR__ . '/../src/AjaxResponse.php';

if (! interface_exists('Psr\Container\ContainerInterface')) {
    eval('namespace Psr\Container; interface ContainerInterface { public function get(string $id); public function has(string $id): bool; }');
}

use Hyperfjax\Exception\ValidationException as HyperfjaxValidationException;
use Hyperfjax\Support\ExceptionMapper;
use Hyperfjax\Support\MethodInvoker;
use Psr\Container\ContainerInterface;

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

$response = ajax()
    ->data(['answer' => 42])
    ->update(['#message' => 'Saved'])
    ->browserEvent('profile:saved', ['ok' => true])
    ->toArray();

assert_true($response['answer'] === 42, 'response data is merged at top level');
assert_true($response['__ajax']['ok'] === true, 'response is ok by default');
assert_true($response['__ajax']['ops'][0]['op'] === 'patchDom', 'patchDom op is emitted');
assert_true($response['__ajax']['ops'][0]['selector'] === '#message', 'selector is preserved');
assert_true($response['__ajax']['ops'][1]['op'] === 'dispatch', 'browser event op is emitted');

$shortcut = ajax()->dataWithUpdateSelectors([
    '@#list' => '<li>New</li>',
    'count' => 1,
])->toArray();

assert_true($shortcut['count'] === 1, 'non-selector data remains data');
assert_true($shortcut['__ajax']['ops'][0]['swap'] === 'append', 'append selector shortcut is supported');
assert_true($shortcut['__ajax']['ops'][0]['selector'] === '#list', 'selector modifier is stripped');

$validationException = new class('Invalid data') extends Exception {
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

$packageValidationException = new HyperfjaxValidationException([
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
            public function toArray(): array
            {
                return ['name' => ['Name is required']];
            }
        };
    }
};

$packageValidatorError = (new ExceptionMapper())->map(HyperfjaxValidationException::fromValidator($validatorLike));

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
    public function __construct(private object $service)
    {
    }

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

echo "Smoke tests passed.\n";
