<?php

declare(strict_types=1);

namespace Mercato\Core;

use Closure;
use RuntimeException;

/**
 * Tiny PSR-11-shaped DI container.
 *
 * Sufficient for M0/M1. May be replaced with league/container or pimple in
 * Phase 2 if compelling features are needed.
 */
final class Container
{
    /** @var array<string, Closure(self):mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    /**
     * Bind a factory closure under an identifier.
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->resolved[$id]); // re-binding invalidates cache
    }

    /**
     * Bind a precomputed instance.
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->resolved[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->resolved);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException("Mercato container: unknown id '{$id}'");
        }

        return $this->resolved[$id] = ($this->factories[$id])($this);
    }
}
