<?php

declare(strict_types=1);

namespace {
    $GLOBALS['mercato_test_transients'] = [];

    if (!function_exists('get_transient')) {
        function get_transient(string $key): mixed
        {
            return $GLOBALS['mercato_test_transients'][$key] ?? false;
        }
    }

    if (!function_exists('set_transient')) {
        function set_transient(string $key, mixed $value, int $expiration): bool
        {
            $GLOBALS['mercato_test_transients'][$key] = $value;
            return true;
        }
    }
}

namespace Mercato\Tests\Unit {
    use Mercato\Core\Rest\RateLimiter;
    use PHPUnit\Framework\TestCase;
    use ReflectionClass;

    require_once dirname(__DIR__, 3) . '/apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/Rest/RateLimiter.php';

    final class RateLimiterTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['mercato_test_transients'] = [];
            $_GET = [];
            $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
            $_SERVER['REQUEST_URI'] = '/?rest_route=/mercato/v1/vendors';

            $reflection = new ReflectionClass(RateLimiter::class);
            $policy = $reflection->getProperty('policy');
            $policy->setAccessible(true);
            $policy->setValue(null, [
                'default' => ['limit' => 1, 'window_seconds' => 60],
            ]);
        }

        protected function tearDown(): void
        {
            $_GET = [];
        }

        public function test_rest_route_query_param_is_used_for_rate_limit_bucket(): void
        {
            $_GET['rest_route'] = '/mercato/v1/vendors';
            self::assertTrue(RateLimiter::allow('default'));
            self::assertFalse(RateLimiter::allow('default'));

            $_GET['rest_route'] = '/mercato/v1/products';
            self::assertTrue(RateLimiter::allow('default'));
        }
    }
}
