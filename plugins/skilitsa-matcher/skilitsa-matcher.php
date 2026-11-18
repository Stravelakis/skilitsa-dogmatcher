<?php
/**
 * Plugin Name:       Skilitsa Matcher
 * Description:       The public dog-matching quiz. Requires Skilitsa Core.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Skilitsa
 * Author URI:        https://skilitsa.com
 * License:           GPL-3.0-or-later
 * Text Domain:       skilitsa-matcher
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const SKILITSA_MATCHER_VERSION      = '2.0.0';
const SKILITSA_MATCHER_REQUIRES_CORE = '2.0.0';

$skilitsa_matcher_autoload = __DIR__ . '/vendor/autoload.php';

if (!is_readable($skilitsa_matcher_autoload)) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__(
                'Skilitsa Matcher: dependencies are missing. Run "composer install" in the plugin folder.',
                'skilitsa-matcher'
            )
            . '</p></div>';
    });

    return;
}

require_once $skilitsa_matcher_autoload;

/**
 * Refuse to boot without a compatible core, with a notice rather than a fatal.
 *
 * A feature plugin that hard-crashes when its dependency is missing takes the
 * whole site with it; an admin notice leaves the site up and says what to do.
 */
function skilitsa_matcher_core_is_ready(): bool
{
    return function_exists('skilitsa_core')
        && defined('SKILITSA_CORE_VERSION')
        && version_compare(SKILITSA_CORE_VERSION, SKILITSA_MATCHER_REQUIRES_CORE, '>=');
}

add_action('plugins_loaded', static function (): void {
    if (!skilitsa_matcher_core_is_ready()) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %s: required Skilitsa Core version */
                    __(
                        'Skilitsa Matcher needs Skilitsa Core %s or newer to be installed and active.',
                        'skilitsa-matcher'
                    ),
                    SKILITSA_MATCHER_REQUIRES_CORE
                ))
            );
        });

        return;
    }

    (new \Skilitsa\Matcher\Plugin(__FILE__))->boot();
}, 20);

register_activation_hook(__FILE__, [\Skilitsa\Matcher\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\Skilitsa\Matcher\Plugin::class, 'deactivate']);
