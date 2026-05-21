<?php

declare(strict_types=1);

namespace Mercato\Core\DB;

use Mercato\Core\Container;
use Mercato\Core\ModuleRegistry;
use RuntimeException;

final class Migrator
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return list<string> Applied migration identifiers.
     */
    public function migrate(): array
    {
        global $wpdb;

        if (!isset($wpdb)) {
            throw new RuntimeException('Mercato migrator requires WordPress $wpdb.');
        }

        $this->ensureRegistryTable();

        $registry = new ModuleRegistry(\MERCATO_SUITE_DIR . '/modules');
        $registry->discover();

        $applied = [];

        foreach ($registry->ordered() as $manifest) {
            $migrationDir = \MERCATO_SUITE_DIR . '/modules/' . $manifest->slug . '/migrations';
            $files = \glob($migrationDir . '/*.sql') ?: [];
            \sort($files);

            foreach ($files as $file) {
                $version = \basename($file, '.sql');
                $checksum = \hash_file('sha256', $file);

                if ($this->isApplied($manifest->slug, $version, $checksum)) {
                    continue;
                }

                $sql = \file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException("Unable to read migration {$file}");
                }

                $this->executeSql($sql);
                $this->record($manifest->slug, $version, $checksum);
                $applied[] = $manifest->slug . ':' . $version;
            }
        }

        return $applied;
    }

    public function verify(): bool
    {
        global $wpdb;

        if (!isset($wpdb)) {
            return false;
        }

        $table = $this->table('mercato_migrations');
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function ensureRegistryTable(): void
    {
        $table = $this->table('mercato_migrations');
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
          `migration_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `plugin` VARCHAR(64) NOT NULL,
          `version_from` VARCHAR(32) DEFAULT NULL,
          `version_to` VARCHAR(32) NOT NULL,
          `applied_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
          `checksum` VARBINARY(32) NOT NULL,
          PRIMARY KEY (`migration_id`),
          UNIQUE KEY `uk_plugin_version` (`plugin`, `version_to`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";

        $this->executeStatement($sql);
    }

    private function isApplied(string $plugin, string $version, string $checksum): bool
    {
        global $wpdb;

        $table = $this->table('mercato_migrations');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT HEX(`checksum`) AS checksum FROM `{$table}` WHERE `plugin` = %s AND `version_to` = %s", $plugin, $version),
            ARRAY_A
        );

        if (!$row) {
            return false;
        }

        $stored = \strtolower((string) $row['checksum']);
        if ($stored !== \strtolower($checksum)) {
            throw new RuntimeException("Migration checksum mismatch for {$plugin}:{$version}");
        }

        return true;
    }

    private function record(string $plugin, string $version, string $checksum): void
    {
        global $wpdb;

        $table = $this->table('mercato_migrations');
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO `{$table}` (`plugin`, `version_to`, `checksum`) VALUES (%s, %s, UNHEX(%s))",
                $plugin,
                $version,
                $checksum
            )
        );
    }

    private function executeSql(string $sql): void
    {
        $sql = \str_replace('{prefix}', $this->prefix(), $sql);
        $sql = \preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = \array_filter(\array_map('trim', \explode(';', $sql)));

        foreach ($statements as $statement) {
            if ($statement === '' || \str_starts_with($statement, '--')) {
                continue;
            }
            $this->executeStatement($statement);
        }
    }

    private function executeStatement(string $sql): void
    {
        global $wpdb;

        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Migration failed: ' . (string) $wpdb->last_error);
        }
    }

    private function table(string $name): string
    {
        return $this->prefix() . $name;
    }

    private function prefix(): string
    {
        global $wpdb;
        return (string) $wpdb->prefix;
    }
}
