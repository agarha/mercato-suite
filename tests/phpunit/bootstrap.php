<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the mercato-core source files so unit tests can exercise the
 * registry + DI + manifest without a full WordPress install.
 *
 * Integration tests that need WP run via wp-env and bypass this bootstrap.
 */

$plugin = dirname(__DIR__, 2) . '/apps/wordpress/wp-content/plugins/mercato-suite';

require_once $plugin . '/modules/mercato-core/src/Container.php';
require_once $plugin . '/modules/mercato-core/src/ModuleManifest.php';
require_once $plugin . '/modules/mercato-core/src/ModuleRegistry.php';
require_once $plugin . '/modules/mercato-core/src/ServiceProvider.php';
require_once $plugin . '/modules/mercato-core/src/Bootstrap.php';
require_once $plugin . '/modules/mercato-core/src/Tenant/Resolver.php';
require_once $plugin . '/modules/mercato-core/src/Tenant/IntegrationSettings.php';

if (!defined('MERCATO_SUITE_VERSION')) {
    define('MERCATO_SUITE_VERSION', 'test');
}
if (!defined('MERCATO_SUITE_FILE')) {
    define('MERCATO_SUITE_FILE', $plugin . '/mercato-suite.php');
}
