<?php
namespace App\Core;

final class Env
{
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') continue;
            $value = trim($value, "\"'");
            self::$values[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }

    public static function string(string $key, string $default = ''): string { return (string) self::get($key, $default); }
    public static function int(string $key, int $default = 0): int { return (int) self::get($key, $default); }
    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOL);
    }
    public static function all(bool $maskSecrets = true): array
    {
        $values = self::$values;
        if ($maskSecrets) foreach ($values as $key => $value) {
            if (preg_match('/SECRET|TOKEN|PASSWORD|WEBHOOK|KEY/i', $key) && $value !== '') $values[$key] = str_repeat('*', min(12, strlen((string)$value)));
        }
        ksort($values);
        return $values;
    }
}
