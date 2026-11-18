<?php
/**
 * Plugin Name:       Skilitsa Core
 * Description:       Breed data, trait matrix and the matching engine. Required by Skilitsa Matcher.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Skilitsa
 * Author URI:        https://skilitsa.com
 * License:           GPL-3.0-or-later
 * Text Domain:       skilitsa-core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SKILITSA_CORE_VERSION = '2.0.0';
const SKILITSA_CORE_FILE    = __FILE__;

$skilitsa_core_autoload = __DIR__ . '/vendor/autoload.php';

if (!is_readable($skilitsa_core_autoload)) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__(
                'Skilitsa Core: dependencies are missing. Run "composer install" in the plugin folder.',
                'skilitsa-core'
            )
            . '</p></div>';
    });

    return;
}

require_once $skilitsa_core_autoload;

register_activation_hook(__FILE__, [\Skilitsa\Core\Data\Installer::class, 'activate']);

/**
 * The booted plugin instance.
 *
 * Feature plugins call skilitsa_core() rather than constructing their own, so
 * the per-request source caches are shared across the page load.
 */
function skilitsa_core(): \Skilitsa\Core\WordPress\Plugin
{
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new \Skilitsa\Core\WordPress\Plugin();
    }

    return $plugin;
}

add_action('plugins_loaded', static function (): void {
    skilitsa_core()->boot();
});
