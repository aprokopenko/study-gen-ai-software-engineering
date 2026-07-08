<?php

declare(strict_types=1);

/**
 * Laravel-style config helper.
 *
 * Usage:
 *   config('database')              → entire database config array
 *   config('database.type')         → 'sqlite'
 *   config('database.missing', 'x') → 'x'
 */
function config(string $key, mixed $default = null): mixed
{
    static $cache = [];

    [$file, $rest] = array_pad(explode('.', $key, 2), 2, null);

    if (!isset($cache[$file])) {
        $path = __DIR__ . '/../config/' . $file . '.php';
        $cache[$file] = file_exists($path) ? require $path : [];
    }

    if ($rest === null) {
        return $cache[$file];
    }

    $value = $cache[$file];
    foreach (explode('.', $rest) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * Resolve a class from the DI container.
 *
 * Usage:
 *   container(Database::class)
 */
function container(string $abstract): mixed
{
    return \App\Services\ContainerFactory::get()->make($abstract);
}
