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

foreach ($expectedModules as $expec