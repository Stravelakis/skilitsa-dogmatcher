<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Screen;

use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Admin\Form\BreedValuesForm;
use Skilitsa\Core\Admin\View\BreedRowBuilder;
use Skilitsa\Core\Data\Wpdb\BreedAdminRepository;

/**
 * The breed list, and the per-breed value editor behind it.
 *
 * A shim: reads the request, hands it to pure classes, renders what comes back.
 * A plain table rather than WP_List_Table - 257 rows with a search box need no
 * bulk actions, and WP_List_Table would put presentation decisions in a place
 * no test can reach.
 */
final class BreedListScreen
{
    private const NONCE = 'skilitsa_breed_values';

    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('You do not have permission to view this page.');
        }

        $repository = new BreedAdminRepository();
        $editId     = isset($_GET['breed']) ? (int) $_GET['breed'] : 0;

        if ($editId > 0) {
            $this->renderEditor($repository, $editId);

            return;
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $rows = BreedRowBuilder::build(
            $repository->posts(),
            $repository->valuesByPost(),
            array_keys($repository->traitLabels()),
            $search
        );

        $incomplete = BreedRowBuilder::incompleteCount($rows);

        require dirname(__DIR__) . '/templates/breed-list.php';
    }

    private function renderEditor(BreedAdminRepository $repository, int $postId): void
    {
        $labels     = $repository->traitLabels();
        $submission = null;
        $savedCount = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            check_admin_referer(self::NONCE);

            $submission = BreedValuesForm::parse(wp_unslash($_POST), array_keys($labels));

            if ($submission->isValid()) {
                $savedCount = $repository->saveValues($postId, $submission->values);
                $submission = null;
            }
        }

        $post   = get_post($postId);
        $values = $repository->valuesFor($postId);

        $nonceField = self::NONCE;
        $listUrl    = admin_url('admin.php?page=' . AdminMenu::SLUG . '-breeds');

        require dirname(__DIR__) . '/templates/breed-edit.php';
    }
}
