<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Screen;

use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Admin\Form\TraitMatrixForm;
use Skilitsa\Core\Admin\View\TraitMatrixViewModel;
use Skilitsa\Core\Data\Wpdb\TraitTuningRepository;

/**
 * The trait matrix screen. A shim: reads the request, hands it to a pure class,
 * writes what comes back.
 */
final class TraitMatrixScreen
{
    private const NONCE = 'skilitsa_trait_matrix';

    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('You do not have permission to view this page.');
        }

        $repository = new TraitTuningRepository();
        $submission = null;
        $savedCount = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            check_admin_referer(self::NONCE);

            $submission = TraitMatrixForm::parse(wp_unslash($_POST));

            if ($submission->isValid()) {
                $savedCount = $repository->save($submission->rows);
                $submission = null;
            }
        }

        $model = TraitMatrixViewModel::build($repository->all(), $submission);

        $nonceField = self::NONCE;

        require dirname(__DIR__) . '/templates/trait-matrix.php';
    }
}
