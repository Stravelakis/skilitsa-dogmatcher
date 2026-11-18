<?php
/**
 * Breed list. Echoes only.
 *
 * @var list<\Skilitsa\Core\Admin\View\BreedRow> $rows
 * @var string                                   $search
 * @var int                                      $incomplete
 */
declare(strict_types=1);

use Skilitsa\Core\Admin\View\ScreenHelp;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap skilitsa-admin">
    <h1><?php echo esc_html(ScreenHelp::title('skilitsa-breeds')); ?></h1>

    <p class="skilitsa-intro"><?php echo esc_html(ScreenHelp::intro('skilitsa-breeds')); ?></p>

    <?php if ($incomplete > 0) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo (int) $incomplete; ?></strong>
                <?php echo $incomplete === 1 ? 'breed is' : 'breeds are'; ?>
                missing trait values.
            </p>
        </div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="skilitsa-breeds">
        <p class="search-box">
            <label class="screen-reader-text" for="skilitsa-breed-search">Search breeds</label>
            <input
                type="search"
                id="skilitsa-breed-search"
                name="s"
                value="<?php echo esc_attr($search); ?>"
                placeholder="Greek name, English name or slug"
            >
            <?php submit_button('Search', '', '', false); ?>
        </p>
    </form>

    <p class="skilitsa-summary"><?php echo count($rows); ?> breeds shown.</p>

    <table class="widefat skilitsa-breeds">
        <thead>
        <tr>
            <th scope="col">Breed</th>
            <th scope="col">Slug</th>
            <th scope="col">Traits</th>
            <th scope="col">Missing</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) : ?>
            <tr class="<?php echo esc_attr($row->isComplete ? '' : 'is-incomplete'); ?>">
                <th scope="row">
                    <a href="<?php echo esc_url(add_query_arg('breed', $row->postId)); ?>">
                        <?php echo esc_html($row->greekName); ?>
                    </a>
                    <?php if ($row->englishName !== '') : ?>
                        <span class="skilitsa-en"><?php echo esc_html($row->englishName); ?></span>
                    <?php endif; ?>
                </th>
                <td><code><?php echo esc_html($row->slug); ?></code></td>
                <td><?php echo (int) $row->valueCount; ?> / <?php echo (int) $row->traitCount; ?></td>
                <td class="skilitsa-missing">
                    <?php if ($row->isComplete) : ?>
                        &mdash;
                    <?php else : ?>
                        <?php echo esc_html(implode(', ', $row->missingTraits)); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if ($rows === []) : ?>
            <tr><td colspan="4">Nothing matched that search.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
