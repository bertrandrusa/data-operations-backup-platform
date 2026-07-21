<?php

declare(strict_types=1);

namespace DataOps;

final class Config
{
    public static function get(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if ($default === null) {
                throw new \RuntimeException("Missing required environment variable: {$key}");
            }

            return $default;
        }

        return $value;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }
}

