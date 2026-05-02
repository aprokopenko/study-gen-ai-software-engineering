<?php

declare(strict_types=1);

use App\Services\ContainerFactory;

function config(string $key, mixed $default = null): mixed
{
    /** @var array<string,mixed> */
    static $cache = [];

    $segments = explode('.', $key);
    $file = (string) array_shift($segments);

    if (!isset($cache[$file])) {
        $path = __DIR__ . '/../config/' . $file . '.php';
        $cache[$file] = file_exists($path) ? require $path : [];
    }

    if (empty($segments)) {
        return $cache[$file];
    }

    $value = $cache[$file];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function container(string $abstract): mixed
{
    return ContainerFactory::make()->get($abstract);
}