<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Frontend;

use Skilitsa\Matcher\Rest\MatchController;

final class Assets
{
    public const HANDLE = 'skilitsa-matcher';

    public function __construct(private readonly string $pluginFile)
    {
    }

    public function register(): void
    {
        $url = plugin_dir_url($this->pluginFile);

        wp_register_style(self::HANDLE, $url . 'assets/matcher.css', [], $this->version('assets/matcher.css'));
        wp_register_script(self::HANDLE, $url . 'assets/matcher.js', [], $this->version('assets/matcher.js'), true);

        wp_localize_script(self::HANDLE, 'SkilitsaMatcher', [
            'restUrl' => esc_url_raw(rest_url(MatchController::NAMESPACE)),
        ]);
    }

    /**
     * The file's own modification time, not the plugin version.
     *
     * The plugin deploys by file copy and its version rarely moves, so a
     * version-numbered asset URL means every browser and every page cache
     * keeps serving yesterday's CSS. An hour was lost to exactly that.
     */
    private function version(string $relative): string
    {
        $path = plugin_dir_path($this->pluginFile) . $relative;
        $time = is_file($path) ? filemtime($path) : false;

        if ($time !== false) {
            return (string) $time;
        }

        return defined('SKILITSA_MATCHER_VERSION') ? SKILITSA_MATCHER_VERSION : '2.0.0';
    }

    /**
     * The owner's accent colour, as a custom property on the quiz only.
     *
     * Inline rather than in the stylesheet because it is the one value that
     * changes per site, and a stylesheet that has to be rewritten to change a
     * colour is a stylesheet that cannot be cached.
     */
    private function accentStyle(): string
    {
        $stored = get_option(\Skilitsa\Core\Data\Settings::OPTION_NAME, []);
        $accent = is_array($stored) ? ($stored['accent_color'] ?? null) : null;

        if (!\Skilitsa\Core\Data\Settings::isHexColour($accent)) {
            return '';
        }

        return sprintf('.skilitsa-matcher{--sk-accent:%s;}', $accent);
    }

    public function enqueue(): void
    {
        wp_enqueue_style(self::HANDLE);

        $accent = $this->accentStyle();
        if ($accent !== '') {
            wp_add_inline_style(self::HANDLE, $accent);
        }
        wp_enqueue_script(self::HANDLE);
    }
}
