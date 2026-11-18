<?php
/**
 * Settings screen. Echoes only - every decision was made in SettingsViewModel.
 *
 * @var \Skilitsa\Core\Admin\View\SettingsViewModel $model
 * @var bool                                        $saved
 * @var string                                      $nonceField
 */
declare(strict_types=1);

use Skilitsa\Core\Admin\View\ScreenHelp;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap skilitsa-admin">
    <h1><?php echo esc_html(ScreenHelp::title('skilitsa')); ?></h1>

    <p class="skilitsa-intro"><?php echo esc_html(ScreenHelp::intro('skilitsa')); ?></p>

    <?php if ($saved) : ?>
        <div class="notice notice-success"><p>Settings saved.</p></div>
    <?php endif; ?>

    <?php if ($model->hasErrors()) : ?>
        <div class="notice notice-error">
            <p>Nothing was saved. Check the fields with a red message.</p>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field($nonceField); ?>

        <div class="skilitsa-grid">
            <?php foreach ($model->fields as $field) : ?>
                <?php
                $classes = 'skilitsa-field';
                $classes .= $field->error !== null ? ' has-error' : '';
                $classes .= $field->isChanged ? ' is-changed' : '';
                ?>
                <div class="<?php echo esc_attr($classes); ?>">
                    <label for="skilitsa-<?php echo esc_attr($field->key); ?>">
                        <?php echo esc_html($field->label); ?>
                    </label>

                    <?php if ($field->type === 'text') : ?>
                        <input
                            type="text"
                            class="skilitsa-wide"
                            id="skilitsa-<?php echo esc_attr($field->key); ?>"
                            name="<?php echo esc_attr($field->key); ?>"
                            value="<?php echo esc_attr($field->value); ?>"
                            spellcheck="false"
                            aria-describedby="skilitsa-help-<?php echo esc_attr($field->key); ?>"
                        >
                    <?php elseif ($field->type === 'color') : ?>
                        <span class="skilitsa-color">
                            <input
                                type="color"
                                id="skilitsa-<?php echo esc_attr($field->key); ?>"
                                value="<?php echo esc_attr($field->value); ?>"
                                data-skilitsa-color-for="skilitsa-<?php echo esc_attr($field->key); ?>-hex"
                                aria-describedby="skilitsa-help-<?php echo esc_attr($field->key); ?>"
                            >
                            <input
                                type="text"
                                id="skilitsa-<?php echo esc_attr($field->key); ?>-hex"
                                name="<?php echo esc_attr($field->key); ?>"
                                value="<?php echo esc_attr($field->value); ?>"
                                spellcheck="false"
                                aria-label="Accent colour, as hex"
                            >
                        </span>
                    <?php elseif ($field->type === 'choice') : ?>
                        <select
                            id="skilitsa-<?php echo esc_attr($field->key); ?>"
                            name="<?php echo esc_attr($field->key); ?>"
                            aria-describedby="skilitsa-help-<?php echo esc_attr($field->key); ?>"
                        >
                            <?php foreach ($field->choices as $value => $label) : ?>
                                <option
                                    value="<?php echo esc_attr((string) $value); ?>"
                                    <?php selected($field->value, (string) $value); ?>
                                ><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input
                            type="number"
                            id="skilitsa-<?php echo esc_attr($field->key); ?>"
                            name="<?php echo esc_attr($field->key); ?>"
                            value="<?php echo esc_attr($field->value); ?>"
                            min="<?php echo esc_attr((string) $field->min); ?>"
                            max="<?php echo esc_attr((string) $field->max); ?>"
                            <?php if ($field->step !== null) : ?>
                                step="<?php echo esc_attr((string) $field->step); ?>"
                            <?php endif; ?>
                            aria-describedby="skilitsa-help-<?php echo esc_attr($field->key); ?>"
                        >
                    <?php endif; ?>

                    <p class="skilitsa-help" id="skilitsa-help-<?php echo esc_attr($field->key); ?>">
                        <?php echo esc_html($field->help); ?>
                        <span class="skilitsa-default">
                            Default: <?php echo esc_html($field->default); ?><?php
                            if ($field->isChanged) :
                                ?> · <span class="skilitsa-changed">changed</span><?php
                            endif;
                            ?>
                        </span>
                    </p>

                    <?php if ($field->error !== null) : ?>
                        <p class="skilitsa-error" role="alert">
                            <?php echo esc_html($field->error); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php submit_button('Save', 'primary', 'submit', false); ?>
    </form>
</div>
