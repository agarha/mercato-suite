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

$expectedModules = [
    'mercato-core',
    'mercato-vendors',
    'mercato-products',
    'mercato-orders',
    'mercato-commissions',
    'mercato-payouts',
    'mercato-reviews',
    'mercato-disputes',
    'mercato-messaging',
    'mercato-notifications',
    'mercato-reports',
    'mercato-search',
    'mercato-subscriptions',
    'mercato-tax-engine',
    'mercato-kyc-kyb',
    'mercato-fraud-risk',
    'mercato-ai-copilot',
    'mercato-collaboration',
    'mercato-enterprise',
    'mercato-migration',
    'mercato-stripe-connect',
    'mercato-paypal-marketplace',
    'mercato-twilio',
    'mercato-sendgrid',
    'mercato-postmark',
    'mercato-aws-s3',
    'mercato-taxjar',
    'mercato-avalara',
    'mercato-shippo',
];

foreach ($expectedModules as $expectedModule) {
    if (!in_array($expectedModule, $ordered, true)) {
        fwrite(STDERR, "{$expectedModule} manifest was not discovered\n");
        exit(1);
    }
}

if (count($ordered) !== count($expectedModules)) {
    fwrite(STDERR, 'Expected ' . count($expectedModules) . ' modules, discovered ' . count($ordered) . "\n");
    exit(1);
}

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
$assertBefore('mercato-orders', 'mercato-disputes');
$assertBefore('mercato-products', 'mercato-search');
$assertBefore('mercato-tax-engine', 'mercato-taxjar');
$assertBefore('mercato-tax-engine', 'mercato-avalara');
$assertBefore('mercato-orders', 'mercato-shippo');

echo implode(PHP_EOL, $ordered) . PHP_EOL;
