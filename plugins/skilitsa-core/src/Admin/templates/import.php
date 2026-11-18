<?php
/**
 * Import screen. Echoes only.
 *
 * @var \Skilitsa\Core\Admin\View\ImportDiffViewModel|null $model
 * @var array<string,int>|null                            $applied
 * @var string|null                                       $failure
 * @var bool                                              $fetched
 * @var string                                            $sheetId
 * @var string                                            $nonceField
 */
declare(strict_types=1);

use Skilitsa\Core\Admin\View\ScreenHelp;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap skilitsa-admin">
    <h1><?php echo esc_html(ScreenHelp::title('skilitsa-import')); ?></h1>

    <p class="skilitsa-intro">
        <?php echo esc_html(ScreenHelp::intro('skilitsa-import')); ?>
        Sheet: <code><?php echo esc_html($sheetId); ?></code>
    </p>

    <?php if ($failure !== null) : ?>
        <div class="notice notice-error">
            <p><strong>The sheet could not be read. Nothing was changed.</strong></p>
            <p><?php echo esc_html($failure); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($applied !== null) : ?>
        <div class="notice notice-success">
            <p><strong>Import applied.</strong></p>
            <ul>
                <?php foreach ($applied as $table => $count) : ?>
                    <li><?php echo esc_html($table); ?>: <?php echo (int) $count; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field($nonceField); ?>
        <input type="hidden" name="skilitsa_action" value="preview">
        <?php submit_button('Fetch and preview', 'secondary', 'submit', false); ?>
    </form>

    <?php if ($model !== null) : ?>

        <h2>What would change</h2>

        <?php if ($model->errors !== []) : ?>
            <div class="notice notice-error">
                <p><strong>This import will not run.</strong></p>
                <ul>
                    <?php foreach ($model->errors as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($model->hasLosses()) : ?>
            <div class="notice notice-warning">
                <p><strong>This import would remove data.</strong></p>
                <?php if ($model->disappearingBreeds !== []) : ?>
                    <p>
                        Breeds that would lose their values:
                        <?php echo esc_html(implode(', ', $model->disappearingBreeds)); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <table class="widefat skilitsa-diff">
            <thead>
            <tr>
                <th scope="col">Table</th>
                <th scope="col">Now</th>
                <th scope="col">After</th>
                <th scope="col">Change</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($model->lines as $line) : ?>
                <tr class="<?php echo esc_attr($line->isLoss ? 'is-loss' : ''); ?>">
                    <th scope="row"><?php echo esc_html($line->table); ?></th>
                    <td><?php echo (int) $line->before; ?></td>
                    <td><?php echo (int) $line->after; ?></td>
                    <td><?php echo $line->delta > 0 ? '+' . (int) $line->delta : (int) $line->delta; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($model->preservedTraits !== []) : ?>
            <p class="skilitsa-summary">
                Left untouched, because you tuned them:
                <?php echo esc_html(implode(', ', $model->preservedTraits)); ?>
            </p>
        <?php endif; ?>

        <?php if ($model->warnings !== []) : ?>
            <div class="notice notice-warning">
                <p><strong>Worth knowing, but not blocking:</strong></p>
                <ul>
                    <?php foreach (array_slice($model->warnings, 0, 25) as $warning) : ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($model->warnings) > 25) : ?>
                    <p><?php echo count($model->warnings) - 25; ?> more.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($model->isNoOp()) : ?>
            <p><strong>Nothing would change. There is nothing to apply.</strong></p>
        <?php elseif ($model->canApply) : ?>
            <form method="post">
                <?php wp_nonce_field($nonceField); ?>
                <input type="hidden" name="skilitsa_action" value="apply">
                <?php submit_button('Apply this import', 'primary', 'submit', false); ?>
            </form>
        <?php endif; ?>

    <?php endif; ?>
</div>
