# Hyperf Ajax

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%5E3.2-green.svg)](https://hyperf.io/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)]()
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)]()

Hyperf Ajax is a Hyperf backend package compatible with the Larajax browser protocol.
It lets a normal Hyperf page controller answer HTML-driven AJAX handlers such as
`data-request="onSave"` without creating a separate JSON API route for every UI
interaction.

This repository currently contains the first MVP implementation:

- Hyperf package skeleton with `ConfigProvider`;
- `ajax()` helper;
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

Publish config/assets in a Hyperf application using Hyperf's vendor publish flow.
The package exposes:

- `publish/hyperfajax.php` -> `config/autoload/hyperfajax.php`;
- `resources/dist` -> `public/vendor/hyperfajax`.

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

## Controller Usage

The recommended low-friction entry point is `Hyperf AjaxController` with
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
`examples/Hyperf AjaxTestController.php`.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Zotenme\HyperfAjax\Controller\HyperfAjaxController;

class Hyperf AjaxTestController extends Hyperf AjaxController
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
use App\Controller\Hyperf AjaxTestController;

Router::addRoute(['GET', 'POST'], '/hyperfajax-test', [Hyperf AjaxTestController::class, 'index']);
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
use Hyperf Ajax\Exception\ValidationException;

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

Controllers that use AJAX components should implement `AjaxControllerInterface`.
The trait provides the required methods.

```php
use Hyperf Ajax\Concerns\InteractsWithAjax;
use Hyperf Ajax\Contracts\AjaxControllerInterface;

class ProfileController implements AjaxControllerInterface
{
    use InteractsWithAjax;

    public array $components = [
        ProfileForm::class,
    ];
}
```

Component:

```php
use Hyperf Ajax\Concerns\ViewComponent;
use Hyperf Ajax\Contracts\ViewComponentInterface;

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

For requested partials, define `makePartialForAjax()` on the controller:

```php
protected function makePartialForAjax(string $partial): string
{
    return match ($partial) {
        'profile/message' => '<div data-ajax-partial="profile/message">Updated</div>',
        default => '',
    };
}
```

The frontend sends requested partial names in `X-AJAX-PARTIALS`.

## Tests

Run the lightweight smoke suite:

```bash
composer test
```

or:

```bash
php tests/smoke.php
```

The smoke suite checks framework-neutral behavior. Full Hyperf integration tests
should be added once the target Hyperf application skeleton is chosen.
