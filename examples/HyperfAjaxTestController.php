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
            return $this->ajax()
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
