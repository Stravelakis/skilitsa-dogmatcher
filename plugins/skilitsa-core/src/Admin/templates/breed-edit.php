<?php
/**
 * One breed's 24 trait values. Echoes only.
 *
 * @var \WP_Post|null                                            $post
 * @var array<string,string>                                     $labels
 * @var array<string,float>                                      $values
 * @var \Skilitsa\Core\Admin\Form\BreedValuesSubmission|null      $submission
 * @var int|null                                                 $savedCount
 * @var string                                                   $nonceField
 * @var string                                                   $listUrl
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap skilitsa-admin">
    <h1>
        <?php echo esc_html($post !== null ? $post->post_title : 'Unknown breed'); ?>
    </h1>

    <p><a href="<?php echo esc_url($listUrl); ?>">&larr; Back to breeds</a></p>

    <?php if ($post === null) : ?>
        <div class="notice notice-error"><p>That breed no longer exists.</p></div>
    <?php else : ?>

        <p class="skilitsa-intro">
            Trait values, 1 to 5, exactly as the quiz reads them. A blank box
            leaves that trait as it is. These are overwritten by the next sheet
            import - fix the sheet if a value should stick.
        </p>

        <?php if ($savedCount !== null) : ?>
            <div class="notice notice-success"><p>Saved <?php echo (int) $savedCount; ?> values.</p></div>
        <?php endif; ?>

        <?php if ($submission !== null && !$submission->isValid()) : ?>
            <div class="notice notice-error">
                <p>Nothing was saved. Check the fields with a red message.</p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field($nonceField); ?>

            <div class="skilitsa-grid">
                <?php foreach ($labels as $key => $label) : ?>
                    <?php
                    $typed = $submission->raw[$key] ?? null;
                    $value = $typed ?? (isset($values[$key]) ? rtrim(rtrim(number_format($values[$key], 2, '.', ''), '0'), '.') : '');
                    $error = $submission?->errorFor($key);
                    ?>
                    <div class="skilitsa-field<?php echo $error !== null ? ' has-error' : ''; ?>">
                        <label for="skilitsa-<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($label); ?>
                        </label>
                        <code class="skilitsa-trait-key"><?php echo esc_html($key); ?></code>

                        <input
                            type="number"
                            step="0.5"
                            min="1"
                            max="5"
                            id="skilitsa-<?php echo esc_attr($key); ?>"
                            name="traits[<?php echo esc_attr($key); ?>]"
                            value="<?php echo esc_attr($value); ?>"
                        >

                        <?php if ($value === '') : ?>
                            <p class="skilitsa-help">No value yet.</p>
                        <?php endif; ?>

                        <?php if ($error !== null) : ?>
                            <p class="skilitsa-error" role="alert"><?php echo esc_html($error); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php submit_button('Save', 'primary', 'submit', false); ?>
        </form>

    <?php endif; ?>
</div>
