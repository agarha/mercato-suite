<?php

declare(strict_types=1);

namespace Mercato\Core;

use RuntimeException;

final class ModuleRegistry
{
    /** @var array<string,ModuleManifest> */
    private array $modules = [];

    public function __construct(private readonly string $modulesPath)
    {
    }

    public function discover(): void
    {
        foreach (glob($this->modulesPath . '/*/module.json') ?: [] as $manifestPath) {
            $json = file_get_contents($manifestPath);

            if ($json === false) {
                throw new RuntimeException("Unable to read module manifest: {$manifestPath}");
            }

            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $manifest = ModuleManifest::fromArray($data);
            $this->modules[$manifest->slug] = $manifest;
        }
    }

    /**
     * @return list<ModuleManifest>
     */
    public function ordered(): array
    {
        $visited = [];
        $visiting = [];
        $ordered = [];

        foreach (array_keys($this->modules) as $slug) {
            $this->visit($slug, $visited, $visiting, $ordered);
        }

        return $ordered;
    }

    /**
     * @param array<string,bool> $visited
     * @param array<string,bool> $visiting
     * @param list<ModuleManifest> $ordered
     */
    private function visit(string $slug, array &$visited, array &$visiting, array &$ordered): void
    {
        if (isset($visited[$slug])) {
            return;
        }

        if (isset($visiting[$slug])) {
            throw new RuntimeException("Mercato module dependency cycle detected at {$slug}");
        }

        if (!isset($this->modules[$slug])) {
            throw new RuntimeException("Mercato module dependency missing: {$slug}");
        }

        $visiting[$slug] = true;

        foreach ($this->modules[$slug]->requires as $requirement) {
            $requiredSlug = preg_replace('/@.*$/', '', $requirement);
            $this->visit((string) $requiredSlug, $visited, $visiting, $ordered);
        }

        unset($visiting[$slug]);
        $visited[$slug] = true;
        $ordered[] = $this->modules[$slug];
    }
}
