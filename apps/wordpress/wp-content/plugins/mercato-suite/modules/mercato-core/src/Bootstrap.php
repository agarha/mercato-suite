<?php

declare(strict_types=1);

namespace Mercato\Core;

final class Bootstrap
{
    public function __construct(private readonly string $modulesPath)
    {
    }

    public function boot(): void
    {
        $registry = new ModuleRegistry($this->modulesPath);
        $registry->discover();

        foreach ($registry->ordered() as $manifest) {
            do_action('mercato_module_discovered', $manifest->slug, $manifest);
        }

        do_action('mercato_suite_booted', MERCATO_SUITE_VERSION);
    }
}
