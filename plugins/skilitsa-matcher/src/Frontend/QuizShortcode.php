<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Frontend;

/**
 * [skilitsa_matcher]
 *
 * Renders a mount point and nothing else. Everything the visitor sees is built
 * from data fetched over REST, so the breed matrix never reaches the page
 * source - v1 inlined 397 KB of it on every load.
 */
final class QuizShortcode
{
    public const TAG = 'skilitsa_matcher';

    public function __construct(private readonly Assets $assets)
    {
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    public function render(): string
    {
        $this->assets->enqueue();

        // The noscript copy is the real fallback, not an apology: without
        // JavaScript there is no quiz, and saying so plainly beats an empty box.
        return '<div class="skilitsa-matcher" data-skilitsa-matcher>'
            . '<noscript><p>'
            . esc_html__(
                'Το τεστ χρειάζεται JavaScript. Ενεργοποίησέ την και ανανέωσε τη σελίδα.',
                'skilitsa-matcher'
            )
            . '</p></noscript>'
            . '</div>';
    }
}
