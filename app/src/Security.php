<?php

declare(strict_types=1);

namespace DataOps;

final class Security
{
    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals((string) $_SESSION['csrf_token'], $token);
    }

    public static function validUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public static function pathInside(string $path, string $root): bool
    {
        if ($path === '' || $root === '' || $path[0] !== '/' || $root[0] !== '/') {
            return false;
        }

        $normalize = static function (string $candidate): string {
            $parts = [];
            foreach (explode('/', $candidate) as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..') {
                    array_pop($parts);
                    continue;
                }
                $parts[] = $part;
            }

            return '/' . implode('/', $parts);
        };

        $cleanPath = $normalize($path);
        $cleanRoot = rtrim($normalize($root), '/');

        return $cleanPath === $cleanRoot || str_starts_with($cleanPath, $cleanRoot . '/');
    }

    public static function clientIp(): string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : '127.0.0.1';
    }
}

