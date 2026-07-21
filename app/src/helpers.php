<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatBytes(int|string|null $bytes): string
{
    $size = max(0, (int) $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = 0;
    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }

    return ($index === 0 ? (string) $size : number_format($size, 1)) . ' ' . $units[$index];
}

function formatDate(?string $date): string
{
    if ($date === null || $date === '') {
        return 'Not yet';
    }

    return (new DateTimeImmutable($date))->format('M j, Y · H:i');
}

function asBool(mixed $value): bool
{
    if (is_string($value)) {
        return in_array(strtolower($value), ['1', 'true', 't', 'yes', 'on'], true);
    }

    return (bool) $value;
}
