<?php

declare(strict_types=1);

require_once __DIR__ . '/../../apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/ModuleManifest.php';
require_once __DIR__ . '/../../apps/wordpress/wp-content/plugins/mercato-suite/modules/mercato-core/src/ModuleRegistry.php';

$registry = new \Mercato\Core\ModuleRegistry(__DIR__ . '/../../apps/wordpress/wp-content/plugins/mercato-suite/modules');
$registry->discover();

$ordered = array_map(
    static fn (\Mercato\Core\ModuleManifest $manifest): string => $manifest->slug,
    $registry->ordered()
);

$positions = array_flip($ordered);
$assertBefore = static function (string $required, string $dependent) use ($positions): void {
    if (!isset($positions[$required], $positions[$dependent]) || $positions[$required] > $positions[$dependent]) {
        fwrite(STDERR, "{$required} must be ordered before {$dependent}\n");
        exit(1);
    }
};

$assertBefore('mercato-core', 'mercato-vendors');
$assertBefore('mercato-vendors', 'mercato-products');
$assertBefore('mercato-products', 'mercato-orders');
$assertBefore('mercato-orders', 'mercato-commissions');
$assertBefore('mercato-stripe-connect', 'mercato-payouts');
$assertBefore('mercato-sendgrid', 'mercato-notifications');
$assertBefore('mercato-aws-s3', 'mercato-kyc-kyb');

echo implode(PHP_EOL, $ordered) . PHP_EOL;
