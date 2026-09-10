<?php

namespace App\Config;

class Env {
    private static bool $loaded = false;

    public static function load(?string $path = null): void {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }

        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);

                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }

                    if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                        putenv("{$name}={$value}");
                        $_ENV[$name] = $value;
                        $_SERVER[$name] = $value;
                    }
                }
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null) {
        self::load();
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }
}
