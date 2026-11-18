<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

use InvalidArgumentException;

/**
 * The one-paragraph answer to "what is this screen for".
 *
 * Kept here rather than in each template so a test can walk every screen the
 * menu registers and fail when one ships without an explanation. The owner is
 * dyslexic and opens these screens rarely; a screen that assumes he remembers
 * last month's reasoning is a screen he will not use.
 */
final class ScreenHelp
{
    /** @var array<string,array{title: string, intro: string}> */
    public const SCREENS = [
        'skilitsa' => [
            'title' => 'Skilitsa settings',
            'intro' => 'How the quiz scores. Every field shows its default; if something goes wrong, put them all back to it.',
        ],
        'skilitsa-traits' => [
            'title' => 'Traits and weights',
            'intro' => 'What a mismatch costs, per trait. Over means the breed has more of it than the visitor asked for; under means less. 0 means it does not matter at all, 1.5 means serious. Rows left on the default re-seed themselves on every import; rows you change stay exactly as you left them.',
        ],
        'skilitsa-breeds' => [
            'title' => 'Breed data',
            'intro' => 'Every breed with a post, and how much of the trait matrix it carries. A breed missing values still appears in results - it just scores on whatever it has, which is worse than useless if that is nothing.',
        ],
        'skilitsa-import' => [
            'title' => 'Import from the sheet',
            'intro' => 'Fetches the Google Sheet and shows exactly what would change before anything is written. Nothing is saved until you press Apply.',
        ],
        'skilitsa-ai' => [
            'title' => 'AI drafts',
            'intro' => 'Suggestions from the AI tasks, waiting for you. Nothing here has been applied: approving one puts it through the same form you would have typed into, and rejecting one changes nothing at all.',
        ],
    ];

    public static function title(string $slug): string
    {
        return self::screen($slug)['title'];
    }

    public static function intro(string $slug): string
    {
        return self::screen($slug)['intro'];
    }

    /** @return array{title: string, intro: string} */
    private static function screen(string $slug): array
    {
        if (!isset(self::SCREENS[$slug])) {
            throw new InvalidArgumentException(sprintf('No help for screen "%s".', $slug));
        }

        return self::SCREENS[$slug];
    }
}
