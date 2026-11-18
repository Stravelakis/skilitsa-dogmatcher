<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

/**
 * Creates and upgrades the plugin's tables.
 *
 * Runs on activation and on any admin request where the stored schema version
 * is behind Schema::VERSION, so a plugin updated by file copy - which is how
 * this one deploys - still migrates.
 */
final class Installer
{
    public const VERSION_OPTION = 'skilitsa_core_schema_version';

    public static function activate(): void
    {
        self::install();
    }

    /** Runs dbDelta if the stored schema version is out of date. */
    public static function maybeUpgrade(): void
    {
        if (get_option(self::VERSION_OPTION) === Schema::VERSION) {
            return;
        }

        self::install();
    }

    private static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Schema::createStatements($wpdb->prefix, $wpdb->get_charset_collate()) as $sql) {
            dbDelta($sql);
        }

        update_option(self::VERSION_OPTION, Schema::VERSION, false);

        if (get_option(Settings::OPTION_NAME) === false) {
            // autoload false: this is read on the quiz and admin pages, not on
            // every request across the site.
            add_option(Settings::OPTION_NAME, Settings::defaults(), '', false);
        }
    }
}
