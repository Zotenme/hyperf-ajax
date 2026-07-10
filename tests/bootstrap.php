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

namespace Swoole {
    if (! class_exists(Coroutine::class, false)) {
        class Coroutine
        {
            private static int $testCid = -1;

            /** @var array<int, \ArrayObject<string, mixed>> */
            private static array $contexts = [];

            public static function getCid(): int
            {
                return self::$testCid;
            }

            public static function setTestCid(int $cid): void
            {
                self::$testCid = $cid;
            }

            /** @return \ArrayObject<string, mixed> */
            public static function getContext(?int $cid = null): \ArrayObject
            {
                $cid ??= self::$testCid;

                return self::$contexts[$cid] ??= new \ArrayObject();
            }
        }
    }
}

namespace {
    require __DIR__ . '/../vendor/autoload.php';
}
