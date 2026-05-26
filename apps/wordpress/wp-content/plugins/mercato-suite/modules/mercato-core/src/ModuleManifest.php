<?php

declare(strict_types=1);

namespace Mercato\Core;

final class ModuleManifest
{
    /**
     * @param list<string> $requires
     * @param list<string> $providesEvents
     * @param list<string> $consumesEvents
     * @param list<string> $capabilities
     * @param list<string> $tables
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $namespace,
        public readonly string $version,
        public readonly array $requires,
        public readonly array $providesEvents,
        public readonly array $consumesEvents,
        public readonly array $capabilities,
        public readonly array $tables,
        public readonly string $tier,
        public readonly string $featureFlag,
        public readonly string $phase,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: (string) $data['slug'],
            namespace: (string) $data['namespace'],
            version: (string) $data['version'],
            requires: array_values($data['requires'] ?? []),
            providesEvents: array_values($data['provides_events'] ?? []),
            consumesEvents: array_values($data['consumes_events'] ?? []),
            capabilities: array_values($data['capabilities'] ?? []),
            tables: array_values($data['tables'] ?? []),
            tier: (string) ($data['tier'] ?? 'domain'),
            featureFlag: (string) ($data['feature_flag'] ?? $data['slug']),
            phase: (string) ($data['phase'] ?? 'P1'),
        );
    }
}
