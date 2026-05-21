<?php
/**
 * Plugin Name: Mercato Suite
 * Description: Mercato marketplace platform plugin bundle.
 * Version: 0.1.0
 * Author: Mercato
 * Requires PHP: 8.2
 * Requires at least: 6.5
 * Text Domain: mercato-suite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('MERCATO_SUITE_VERSION', '0.1.0');
define('MERCATO_SUITE_FILE', __FILE__);
define('MERCATO_SUITE_DIR', __DIR__);

require_once MERCATO_SUITE_DIR . '/modules/mercato-core/src/Container.php';
require_once MERCATO_SUITE_DIR . '/modules/mercato-core/src/ModuleManifest.php';
require_once MERCATO_SUITE_DIR . '/modules/mercato-core/src/ModuleRegistry.php';
require_once MERCATO_SUITE_DIR . '/modules/mercato-core/src/ServiceProvider.php';
require_once MERCATO_SUITE_DIR . '/modules/mercato-core/src/Bootstrap.php';

add_action('plugins_loaded', static function (): void {
    $bootstrap = new \Mercato\Core\Bootstrap(MERCATO_SUITE_DIR . '/modules');
    $bootstrap->boot();
}, 1);
