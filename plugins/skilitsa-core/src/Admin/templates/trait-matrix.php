<?php
/**
 * Trait matrix screen. Echoes only.
 *
 * @var \Skilitsa\Core\Admin\View\TraitMatrixViewModel $model
 * @var int|null                                      $savedCount
 * @var string                                        $nonceField
 */
declare(strict_types=1);

use Skilitsa\Core\Admin\View\ScreenHelp;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap skilitsa-admin">
    <h1><?php echo esc_html(ScreenHelp::title('skilitsa-traits')); ?></h1>

    <p class="skilitsa-intro"><?php echo esc_html(ScreenHelp::intro('skilitsa-traits')); ?></p>

    <?php if ($savedCount !== null) : ?>
        <div class="notice notice-success">
            <p>Saved <?php echo (int) $savedCount; ?> traits.</p>
        </div>
    <?php endif; ?>

    <?php if ($model->hasErrors()) : ?>
        <div class="notice notice-error">
            <p>Nothing was saved. Check the fields with a red message.</p>
        </div>
    <?php endif; ?>

    <p class="skilitsa-summary">
        Your own settings: <strong><?php echo (int) $model->tunedCount(); ?></strong>
        of <?php echo count($model->rows); ?> traits.
    </p>

    <form method="post">
        <?php wp_nonce_field($nonceField); ?>

        <table class="widefat skilitsa-matrix">
            <thead>
            <tr>
                <th scope="col">Trait</th>
                <th scope="col">Over</th>
                <th scope="col">Under</th>
                <th scope="col">Weight</th>
                <th scope="col">State</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($model->rows as $row) : ?>
                <tr class="<?php echo esc_attr($row->hasError() ? 'has-error' : ($row->isTuned ? 'is-tuned' : '')); ?>">
                    <th scope="row">
                        <span class="skilitsa-trait-label"><?php echo esc_html($row->label); ?></span>
                        <code class="skilitsa-trait-key"><?php echo esc_html($row->key); ?></code>
                        <?php if ($row->rationale !== '') : ?>
                            <span class="skilitsa-rationale"><?php echo esc_html($row->rationale); ?></span>
                        <?php endif; ?>
                    </th>

                    <?php foreach ([
                        ['over', 'Over', $row->over, $row->defaultOver, $row->errorOver],
                        ['under', 'Under', $row->under, $row->defaultUnder, $row->errorUnder],
                        ['weight', 'Weight', $row->weight, '1', $row->errorWeight],
                    ] as [$sub, $subLabel, $value, $default, $error]) : ?>
                        <td>
                            <label
                                class="screen-reader-text"
                                for="skilitsa-<?php echo esc_attr($row->key . '-' . $sub); ?>"
                            ><?php echo esc_html($row->label . ' — ' . $subLabel); ?></label>

                            <input
                                type="number"
                                step="0.05"
                                min="0"
                                max="<?php echo $sub === 'weight' ? '5' : '3'; ?>"
                                id="skilitsa-<?php echo esc_attr($row->key . '-' . $sub); ?>"
                                name="traits[<?php echo esc_attr($row->key); ?>][<?php echo esc_attr($sub); ?>]"
                                value="<?php echo esc_attr($value); ?>"
                            >

                            <?php if ($default !== '') : ?>
                                <span class="skilitsa-default">default <?php echo esc_html($default); ?></span>
                            <?php endif; ?>

                            <?php if ($error !== null) : ?>
                                <span class="skilitsa-error" role="alert"><?php echo esc_html($error); ?></span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>

                    <td class="skilitsa-state">
                        <?php echo $row->isTuned ? 'yours' : 'default'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button('Save', 'primary', 'submit', false); ?>
    </form>
</div>
