<?php

declare(strict_types=1);

namespace Mercato\Core\Tenant;

final class Resolver
{
    public function currentTenantId(): int
    {
        if (\defined('MERCATO_TEST_TENANT_ID')) {
            return (int) \MERCATO_TEST_TENANT_ID;
        }

        if (\function_exists('get_current_blog_id')) {
            return (int) \get_current_blog_id();
        }

        return 1;
    }
}
