<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin;

use Skilitsa\Core\Admin\Screen\AiDraftsScreen;
use Skilitsa\Core\Admin\Screen\BreedListScreen;
use Skilitsa\Core\Admin\Screen\ImportScreen;
use Skilitsa\Core\Admin\Screen\SettingsScreen;
use Skilitsa\Core\Admin\Screen\TraitMatrixScreen;

/**
 * Registers the Skilitsa admin pages. A shim: it wires WordPress to screens
 * and decides nothing itself.
 */
final class AdminMenu
{
    public const CAPABILITY = 'manage_options';
    public const SLUG       = 'skilitsa';

    /**
     * Every screen this menu registers. Named here so a test can check each one
     * ships with help text rather than trusting whoever added it.
     *
     * @var list<string>
     */
    public const SCREENS = [
        self::SLUG,
        self::SLUG . '-traits',
        self::SLUG . '-breeds',
        self::SLUG . '-import',
        self::SLUG . '-ai',
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPages']);

        // "Settings" beside Deactivate on the plugins page. Cheaper to reach
        // than hunting the menu, and it is where anyone looks first.
        add_filter(
            'plugin_action_links_' . plugin_basename(SKILITSA_CORE_FILE),
            [$this, 'addSettingsLink']
        );
    }

    /**
     * @param  array<int|string,string> $links
     * @return array<int|string,string>
     */
    public function addSettingsLink(array $links): array
    {
        array_unshift($links, sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . self::SLUG)),
            'Settings'
        ));

        return $links;
    }

    public function addPages(): void
    {
        $hook = add_menu_page(
            'Skilitsa',
            'Skilitsa',
            self::CAPABILITY,
            self::SLUG,
            [new SettingsScreen(), 'render'],
            'dashicons-pets',
            58
        );

        // Only on our own screens: a stylesheet loaded across wp-admin is a
        // stylesheet that eventually breaks someone else's plugin.
        add_action('admin_print_styles-' . $hook, [$this, 'enqueueAssets']);

        add_submenu_page(
            self::SLUG,
            'Settings',
            'Settings',
            self::CAPABILITY,
            self::SLUG,
            [new SettingsScreen(), 'render']
        );

        $matrix = add_submenu_page(
            self::SLUG,
            'Traits',
            'Traits',
            self::CAPABILITY,
            self::SLUG . '-traits',
            [new TraitMatrixScreen(), 'render']
        );

        $breeds = add_submenu_page(
            self::SLUG,
            'Breed data',
            'Breed data',
            self::CAPABILITY,
            self::SLUG . '-breeds',
            [new BreedListScreen(), 'render']
        );

        $import = add_submenu_page(
            self::SLUG,
            'Import',
            'Import',
            self::CAPABILITY,
            self::SLUG . '-import',
            [new ImportScreen(), 'render']
        );

        add_action('admin_print_styles-' . $matrix, [$this, 'enqueueAssets']);
        add_action('admin_print_styles-' . $breeds, [$this, 'enqueueAssets']);
        $ai = add_submenu_page(
            self::SLUG,
            'AI drafts',
            'AI drafts',
            self::CAPABILITY,
            self::SLUG . '-ai',
            [new AiDraftsScreen(), 'render']
        );

        add_action('admin_print_styles-' . $import, [$this, 'enqueueAssets']);
        add_action('admin_print_styles-' . $ai, [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_script(
            'skilitsa-admin',
            plugins_url('src/Admin/assets/admin.js', SKILITSA_CORE_FILE),
            [],
            SKILITSA_CORE_VERSION,
            true
        );

        wp_enqueue_style(
            'skilitsa-admin',
            plugins_url('src/Admin/assets/admin.css', SKILITSA_CORE_FILE),
            [],
            SKILITSA_CORE_VERSION
        );
    }
}
