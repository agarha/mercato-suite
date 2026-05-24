<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Multi-tenancy drift guardrail.
 *
 * Scans every PHP file under modules/<...>/src for double-quoted SQL string
 * literals. For each literal that references a wp_mercato_* table NOT on
 * the exempt list, asserts the string contains the literal "tenant_id".
 *
 * If a future PR adds a new tenant-bound table query without filtering on
 * tenant_id, this test fails the build.
 *
 * If the new query legitimately spans tenants (rare), the fix is to add the
 * table name to TENANT_FREE_TABLES here — forcing an explicit decision
 * documented in the diff rather than silent drift.
 *
 * See docs/reviews/MULTI_TENANCY_AUDIT.md for the full methodology.
 */
final class TenancyDriftTest extends TestCase
{
    /** Tables that legitimately don't need a tenant_id filter. */
    private const TENANT_FREE_TABLES = [
        // Control-plane / global tables
        'mercato_tenants',           // IS the tenants table; PK is tenant_id
        'mercato_capabilities',      // global capability catalogue
        'mercato_event_outbox',      // tenant scope enforced by Outbox writer
        'mercato_audit_log',         // tenant scope enforced by Audit\Writer
        'mercato_idempotency',       // tenant scope enforced by Idempotency\Store
        'mercato_migrations_log',    // global migration ledger

        // Child tables that inherit tenant via parent FK (architectural choice
        // documented in docs/reviews/MULTI_TENANCY_AUDIT.md §3).
        'mercato_messages',          // child of mercato_message_threads (thread_id)
        'mercato_suborder_items',    // child of mercato_suborders (suborder_id)
        'mercato_order_shipments',   // child of mercato_suborders (suborder_id)
        'mercato_payout_items',      // child of mercato_payout_batches (batch_id)
    ];

    public function testEverySqlStringTouchingATenantTableFiltersByTenantId(): void
    {
        $root = dirname(__DIR__, 3);
        $modulesDir = $root . '/apps/wordpress/wp-content/plugins/mercato-suite/modules';
        self::assertDirectoryExists($modulesDir, 'mercato-suite modules dir missing');

        $violations = [];
        $stats = ['sites' => 0, 'scoped' => 0, 'exempt' => 0];

        foreach ($this->phpFiles($modulesDir) as $file) {
            $source = (string) file_get_contents($file);
            foreach ($this->doubleQuotedStrings($source) as [$line, $string]) {
                if (!$this->looksLikeSql($string)) {
                    continue;
                }
                if (stripos($string, 'mercato_') === false) {
                    continue;
                }
                $stats['sites']++;

                if (preg_match('/\btenant_id\b/i', $string) === 1) {
                    $stats['scoped']++;
                    continue;
                }

                // Get the set of mercato_* table names referenced.
                preg_match_all('/\bmercato_[a-z_]+\b/i', $string, $m);
                $tables = array_unique($m[0]);
                if ($tables !== [] && $this->allTablesExempt($tables)) {
                    $stats['exempt']++;
                    continue;
                }

                $rel = ltrim(substr($file, strlen($modulesDir)), '/\\');
                $compact = substr(preg_replace('/\s+/', ' ', $string) ?? '', 0, 180);
                $tableList = implode(',', $tables);
                $violations[] = "  {$rel}:L{$line} [{$tableList}] {$compact}";
            }
        }

        if ($violations !== []) {
            self::fail(
                "Found " . count($violations) . " SQL string(s) that reference a tenant-bound "
                . "mercato_* table without a tenant_id filter. Either filter by tenant_id, or "
                . "if the query is legitimately tenant-free, add the table to "
                . "TenancyDriftTest::TENANT_FREE_TABLES with a comment explaining why.\n\n"
                . implode("\n", $violations)
            );
        }

        // Sanity check: the scanner found *something*, otherwise our regex is broken
        // and a future regression would silently pass.
        self::assertGreaterThan(
            10,
            $stats['scoped'],
            'TenancyDriftTest found <10 tenant-scoped SQL strings — scanner is likely broken'
        );
    }

    /**
     * @param list<string> $tables
     */
    private function allTablesExempt(array $tables): bool
    {
        foreach ($tables as $t) {
            if (!in_array($t, self::TENANT_FREE_TABLES, true)) {
                return false;
            }
        }
        return true;
    }

    private function looksLikeSql(string $s): bool
    {
        return preg_match('/(?i)\b(?:SELECT|UPDATE|DELETE\s+FROM|INSERT\s+INTO)\b/', $s) === 1;
    }

    /**
     * Char-by-char extraction of every double-quoted PHP string literal
     * from the source. Skips // and /* */ comments and single-quoted
     * strings so they can't false-positive.
     *
     * @return \Generator<array{int,string}>
     */
    private function doubleQuotedStrings(string $src): \Generator
    {
        $i = 0;
        $n = strlen($src);
        $line = 1;
        while ($i < $n) {
            $c = $src[$i];
            if ($c === "\n") {
                $line++;
                $i++;
                continue;
            }
            if ($c === '/' && $i + 1 < $n && $src[$i + 1] === '/') {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($c === '/' && $i + 1 < $n && $src[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $n && !($src[$i] === '*' && $src[$i + 1] === '/')) {
                    if ($src[$i] === "\n") {
                        $line++;
                    }
                    $i++;
                }
                $i += 2;
                continue;
            }
            if ($c === "'") {
                $i++;
                while ($i < $n && $src[$i] !== "'") {
                    if ($src[$i] === '\\' && $i + 1 < $n) {
                        $i += 2;
                        continue;
                    }
                    if ($src[$i] === "\n") {
                        $line++;
                    }
                    $i++;
                }
                $i++;
                continue;
            }
            if ($c === '"') {
                $start = $line;
                $i++;
                $buf = '';
                while ($i < $n && $src[$i] !== '"') {
                    if ($src[$i] === '\\' && $i + 1 < $n) {
                        $buf .= $src[$i] . $src[$i + 1];
                        if ($src[$i + 1] === "\n") {
                            $line++;
                        }
                        $i += 2;
                        continue;
                    }
                    if ($src[$i] === "\n") {
                        $line++;
                    }
                    $buf .= $src[$i];
                    $i++;
                }
                $i++;
                yield [$start, $buf];
                continue;
            }
            $i++;
        }
    }

    /**
     * @return \Generator<string>
     */
    private function phpFiles(string $dir): \Generator
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            if ($f->getExtension() !== 'php') {
                continue;
            }
            // Skip tests directories (they contain string literals about SQL we'd
            // false-positive on, like docblock examples or assertion fixtures).
            if (str_contains((string) $f->getPathname(), '/tests/')) {
                continue;
            }
            yield (string) $f->getPathname();
        }
    }
}
