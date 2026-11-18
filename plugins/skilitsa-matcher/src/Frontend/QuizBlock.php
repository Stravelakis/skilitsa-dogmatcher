<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Frontend;

/**
 * The block wrapper around [skilitsa_matcher].
 *
 * A convenience, not a second implementation: the block renders through the
 * same shortcode object, so there is exactly one definition of what the quiz
 * markup is. Anything else and the two drift, and the one nobody tests is the
 * one on the live page.
 *
 * Dynamic by necessity - the quiz is a mount point that a script fills, so
 * there is nothing meaningful to save into post content.
 */
final class QuizBlock
{
    public const NAME = 'skilitsa/matcher';

    /** Editor-only script. No build step: this is plain browser JavaScript. */
    private const EDITOR_HANDLE = 'skilitsa-matcher-block';

    public function __construct(
        private readonly QuizShortcode $shortcode,
        private readonly string $pluginFile,
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        $url = plugin_dir_url($this->pluginFile);
        $ver = defined('SKILITSA_MATCHER_VERSION') ? SKILITSA_MATCHER_VERSION : '2.0.0';

        wp_register_script(
            self::EDITOR_HANDLE,
            $url . 'assets/block.js',
            ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor'],
            $ver,
            true
        );

        register_block_type(self::NAME, [
            'api_version'     => 2,
            'title'           => 'Skilitsa matcher',
            'description'     => 'The breed-matching quiz.',
            'category'        => 'widgets',
            'icon'            => 'pets',
            'editor_script'   => self::EDITOR_HANDLE,
            'supports'        => ['html' => false, 'multiple' => false],
            'render_callback' => [$this, 'render'],
        ]);
    }

    public function render(): string
    {
        return $this->shortcode->render();
    }
}
