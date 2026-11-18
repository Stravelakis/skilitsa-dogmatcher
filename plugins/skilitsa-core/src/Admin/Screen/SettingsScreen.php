<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Screen;

use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Admin\Form\SettingsForm;
use Skilitsa\Core\Admin\View\SettingsViewModel;
use Skilitsa\Core\Data\Settings;

/**
 * The settings screen. A shim: reads the request, hands it to a pure class,
 * renders the answer.
 */
final class SettingsScreen
{
    private const NONCE = 'skilitsa_settings';

    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('You do not have permission to view this page.');
        }

        $submission = null;
        $saved      = false;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            check_admin_referer(self::NONCE);

            $submission = SettingsForm::parse(wp_unslash($_POST));

            if ($submission->isValid()) {
                update_option(Settings::OPTION_NAME, $submission->values, false);
                $saved      = true;
                $submission = null;
            }
        }

        $stored = get_option(Settings::OPTION_NAME, []);
        $model  = SettingsViewModel::build(is_array($stored) ? $stored : [], $submission);

        $nonceField = self::NONCE;

        require dirname(__DIR__) . '/templates/settings.php';
    }
}
