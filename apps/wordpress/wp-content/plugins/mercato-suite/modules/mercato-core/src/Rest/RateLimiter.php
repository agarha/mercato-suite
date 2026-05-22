<?php

declare(strict_types=1);

namespace Mercato\Core\Rest;

final class RateLimiter
{
    /**
     * @var array<string,array{limit:int,window_seconds:int}>|null
     */
    private static ?array $policy = null;

    public static function allow(string $bucket): bool
    {
        $policy = self::policy($bucket);
        $identity = self::identity();
        $route = self::routeKey();
        $window = (int) \floor(\time() / $policy['window_seconds']);
        $key = 'mercato_rl_' . \md5($bucket . '|' . $route . '|' . $identity . '|' . $window);

        $count = (int) \get_transient($key);
        if ($count >= $policy['limit']) {
            return false;
        }

        \set_transient($key, $count + 1, $policy['window_seconds'] + 5);
        return true;
    }

    /**
     * @return array{limit:int,window_seconds:int}
     */
    private static function policy(string $bucket): array
    {
        $policies = self::policies();
        return $policies[$bucket] ?? $policies['default'];
    }

    /**
     * @return array<string,array{limit:int,window_seconds:int}>
     */
    private static function policies(): array
    {
        if (self::$policy !== null) {
            return self::$policy;
        }

        $file = \defined('MERCATO_SUITE_DIR') ? \MERCATO_SUITE_DIR . '/config/rate-limits.json' : '';
        $data = \is_readable($file) ? \json_decode((string) \file_get_contents($file), true) : [];
        if (!\is_array($data)) {
            $data = [];
        }

        self::$policy = [];
        foreach ($data as $bucket => $policy) {
            if (!\is_array($policy)) {
                continue;
            }
            self::$policy[(string) $bucket] = [
                'limit' => \max(1, (int) ($policy['limit'] ?? 120)),
                'window_seconds' => \max(1, (int) ($policy['window_seconds'] ?? 60)),
            ];
        }
        self::$policy['default'] ??= ['limit' => 120, 'window_seconds' => 60];

        return self::$policy;
    }

    private static function identity(): string
    {
        if (\function_exists('get_current_user_id')) {
            $userId = (int) \get_current_user_id();
            if ($userId > 0) {
                return 'user:' . $userId;
            }
        }

        return 'ip:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private static function routeKey(): string
    {
        return (string) \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    }
}
