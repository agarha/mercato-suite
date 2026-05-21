<?php

declare(strict_types=1);

namespace Mercato\Core;

use RuntimeException;

/**
 * Boot sequence:
 *   1. Refuse to boot if environment doesn't satisfy non-negotiables (FR-CORE-001).
 *   2. Discover module manifests.
 *   3. Topologically sort.
 *   4. For each module: load its ServiceProvider (if any) and register() into container.
 *   5. For each module in order: call boot().
 *   6. Fire mercato_suite_booted.
 *
 * See docs_v2/04_fsd/FSD.md §4.
 */
final class Bootstrap
{
    private const REQUIRED_