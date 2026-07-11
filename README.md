# Hyperf Ajax

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%5E3.2-green.svg)](https://hyperf.io/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)]()
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)]()

Hyperf Ajax is a Hyperf backend package compatible with the Larajax browser protocol.
It lets a normal Hyperf page controller answer HTML-driven AJAX handlers such as
`data-request="onSave"` without creating a separate JSON API route for every UI
interaction.

This repository currently contains the first MVP implementation:

- Hyperf package skeleton with `ConfigProvider`;
- Larajax-compatible `__ajax` response envelope;
- DOM patch, partial, redirect, reload, flash, browser event and asset operations;
- explicit controller trait and base controller for `onXxx` handler dispatch;
- component container with `component::onHandler` support;
- validation/error mapper;
- publishable frontend assets copied from Larajax dist.

## Installation

For local development:

```bash
composer require zotenme/hyperf-ajax
```

Publish the frontend assets in a Hyperf application using Hyperf's vendor publish
flow. The package exposes `resources/dist` as `public/vendor/hyperfajax`.

## Frontend

Use either the existing npm package:

```bash
npm install larajax
```

```js
import { jax } from 'larajax';

window.jax = jax;
jax.start();
```

Or serve the published bundle:

```html
<script src="/vendor/hyperfajax/framework-bundle.min.js"></script>
```

`framework-bundle.min.js` starts the framework automatically. Do not call
`window.jax.start()` with this bundle.

The published bundles are redistributed from Larajax 2.2.3. See
[Third-Party Notices](THIRD_PARTY_NOTICES.md) for the upstream copyright and
MIT license notice.

## Controller Usage

The recommended low-friction entry point is `HyperfAjaxController` with
`ajaxPage()`. The method renders a normal page for regular requests and dispatches
`onXxx` handlers for AJAX requests.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;

class ProfileController extends HyperfAjaxController
{
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        return $this->ajaxPage(
            $request,
            $response,
            fn () => view('profile.index'),
            'index'
        );
    }

    public function onSave()
    {
        return $this
            ->ajax()
            ->update(['#message' => 'Saved'])
            ->browserEvent('profile:saved', ['ok' => true]);
    }
}
```

Route:

```php
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST'], '/profile', [ProfileController::class, 'index']);
```

View:

```html
<form>
    <input name="first_name">
    <button data-request="onSave">Save</button>
</form>

<div id="message"></div>
```

## Minimal Browser Test

This example returns a tiny HTML page and a working `onPing` handler. It is the
quickest way to verify a local path install inside a real Hyperf application.

See the copyable source in
`examples/HyperfAjaxTestController.php`.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;

class HyperfAjaxTestController extends HyperfAjaxController
{
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        return $this->ajaxPage($request, $response, function () use ($response) {
            $html = <<<'HTML'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <script src="/vendor/hyperfajax/framework-bundle.min.js"></script>
    <style>
        [data-validate-for],
        [data-validate-error] { display: none; color: #b00020; margin-top: 4px; }
        [data-validate-for].jax-visible,
        [data-validate-error].jax-visible { display: block; }
    </style>
</head>
<body>
    <form data-request="onPing" data-request-validate>
        <input name="name" placeholder="Name">
        <div data-validate-for="name"></div>

        <button type="submit">Ping</button>
    </form>

    <div id="message"></div>
</body>
</html>
HTML;

            return $response->html($html);
        }, 'index');
    }

    public function onPing()
    {
        $name = trim((string) $this->ajaxPost('name', ''));

        if ($name === '') {
            return $this
                ->ajax()
                ->invalidField('name', 'Name is required')
                ->update([
                    '#message' => 'Validation failed',
                ]);
        }

        return $this->ajax()->update([
            '#message' => 'pong, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        ]);
    }
}
```

Route:

```php
use App\Controller\HyperfAjaxTestController;
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST'], '/hyperfajax-test', [HyperfAjaxTestController::class, 'index']);
```

## Request Input

Controllers using `InteractsWithAjax` do not need a `$this->request` property.
The trait exposes request data from the active AJAX request:

```php
public function onSave()
{
    $data = $this->ajaxAll();
    $phone = $this->ajaxPost('phone');

    if (! $phone) {
        return $this->ajax()->invalidField('phone', 'Phone is required');
    }

    return $this->ajax()->update([
        '#message' => 'Saved',
    ]);
}
```

Available helpers:

- `ajaxAll()` returns query and parsed body data, with body values taking priority.
- `ajaxPost()` returns parsed body data.
- `ajaxPost('field', 'default')` returns one parsed body value.
- `ajaxInput('field', 'default')` returns one merged query/body value.

AJAX handler names follow one backend protocol grammar. Controller handlers use
`onEvent`; component handlers use `ComponentAlias::onEvent`. `Event` must start
with an uppercase ASCII letter, and names may otherwise contain ASCII letters,
digits and underscores. Invalid handler names return an AJAX HTTP 400 response.

## Validation

For inline, October-style validation, throw Hyperf Ajax's own validation
exception. It accepts either a field error array or a validator/message-bag-like
object.

Add `data-request-validate` to the form and place `data-validate-for="field"`
where a field-level error should appear:

```html
<form data-request="onSave" data-request-validate>
    <input name="email" placeholder="Email">
    <div data-validate-for="email"></div>

    <button type="submit">Save</button>
</form>
```

```php
use Zotenme\HyperfAjax\Exception\ValidationException;

public function onSave()
{
    $validator = $this->validationFactory->make(
        $this->ajaxPost(),
        [
            'email' => ['required', 'email'],
        ],
        [
            'email.required' => 'Email is required',
            'email.email' => 'Email format is invalid',
        ]
    );

    if ($validator->fails()) {
        throw ValidationException::fromValidator($validator);
    }

    return $this->ajax()->update([
        '#message' => 'Saved',
    ]);
}
```

You can also throw field errors directly:

```php
throw new ValidationException([
    'email' => ['Email is required'],
]);
```

## Components

`HyperfAjaxController` already implements `AjaxControllerInterface`, so a
controller using components only needs to declare its component list:

```php
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;

class ProfileController extends HyperfAjaxController
{
    public array $components = [
        ProfileForm::class,
    ];
}
```

Component aliases must be unique within one AJAX request. Duplicate aliases are
rejected during registration instead of silently replacing an earlier component.

When applying `InteractsWithAjax` directly, the class must implement
`AjaxControllerInterface` and provide the container accessor required by the
trait:

```php
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ContainerInterface;
use Zotenme\HyperfAjax\Concerns\InteractsWithAjax;
use Zotenme\HyperfAjax\Contracts\AjaxControllerInterface;

class ProfileController implements AjaxControllerInterface
{
    use InteractsWithAjax;

    protected function getAjaxContainer(): ContainerInterface
    {
        return ApplicationContext::getContainer();
    }
}
```

Component:

```php
use Zotenme\HyperfAjax\Concerns\ViewComponent;
use Zotenme\HyperfAjax\Contracts\ViewComponentInterface;

class ProfileForm implements ViewComponentInterface
{
    use ViewComponent;

    public function onSave()
    {
        return $this->ajax()->update(['#message' => 'Saved from component']);
    }
}
```

HTML:

```html
<button data-request="ProfileForm::onSave">Save</button>
```

## Partials

You can always return ready HTML without a renderer:

```php
return $this->ajax()->partial(
    'profile/message',
    '<div data-ajax-partial="profile/message">Updated</div>'
);
```

For automatic rendering of names sent in `X-AJAX-PARTIALS`, bind a
`PartialRendererInterface` implementation in `config/autoload/dependencies.php`:

```php
use App\Ajax\PlainPhpPartialRenderer;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

return [
    PartialRendererInterface::class => PlainPhpPartialRenderer::class,
];
```

The renderer is framework-neutral and receives the controller, current AJAX
request and explicit template data:

```php
use Zotenme\HyperfAjax\AjaxRequest;
use Zotenme\HyperfAjax\Contracts\PartialRendererInterface;

final class PlainPhpPartialRenderer implements PartialRendererInterface
{
    public function render(
        string $partial,
        object $controller,
        AjaxRequest $request,
        array $data = []
    ): string {
        return '<div data-ajax-partial="' . htmlspecialchars($partial) . '">'
            . htmlspecialchars((string) ($data['message'] ?? ''))
            . '</div>';
    }
}
```

Set data during the AJAX handler. It is stored only in the current coroutine
execution context:

```php
public function onSave(): AjaxResponse
{
    $this->withAjaxPartialData(['message' => 'Profile saved']);

    return $this->ajax()->data(['saved' => true]);
}
```

### Using hyperf/view

Install and configure `hyperf/view` and your chosen view engine, then implement
the same contract with `Hyperf\View\RenderInterface::getContents()`:

```php
use Hyperf\View\RenderInterface;

final class HyperfViewPartialRenderer implements PartialRendererInterface
{
    public function __construct(private readonly RenderInterface $view) {}

    public function render(
        string $partial,
        object $controller,
        AjaxRequest $request,
        array $data = []
    ): string {
        $view = match ($partial) {
            'profile/message' => 'partials.profile-message',
            default => throw new InvalidArgumentException("Unknown partial [{$partial}]."),
        };

        return $this->view->getContents($view, $data);
    }
}
```

Complete examples for direct strings, plain PHP templates and `hyperf/view`
are available in [`examples/PartialRendering`](examples/PartialRendering).

## Tests

Run the PHPUnit suite:

```bash
composer test
```

The suite checks framework-neutral behavior. Full Hyperf integration tests remain
in the local, ignored `hyperf-app` smoke environment.
