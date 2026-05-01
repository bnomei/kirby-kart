<?php

declare(strict_types=1);

namespace {
    use Dotenv\Dotenv;

    $root = dirname(__DIR__);

    if (class_exists(Dotenv::class) && is_file($root.'/.env')) {
        Dotenv::createUnsafeImmutable($root)->safeLoad();
    }
}

namespace Bnomei {
    if (! class_exists(DotEnv::class, false)) {
        final class DotEnv
        {
            public static function getenv(string $key, mixed $default = null): mixed
            {
                $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

                return $value === false ? $default : $value;
            }
        }
    }
}
