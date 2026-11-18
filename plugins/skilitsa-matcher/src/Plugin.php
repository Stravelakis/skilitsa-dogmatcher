<?php
declare(strict_types=1);

namespace Skilitsa\Matcher;

use Skilitsa\Matcher\Content\ContentProvider;
use Skilitsa\Matcher\Frontend\Assets;
use Skilitsa\Matcher\Frontend\QuizBlock;
use Skilitsa\Matcher\Frontend\QuizShortcode;
use Skilitsa\Matcher\Frontend\ResultsRoute;
use Skilitsa\Matcher\Rest\MatchController;
use Skilitsa\Matcher\Session\SessionRepository;
use Skilitsa\Matcher\Telemetry\EventRecorder;
use Skilitsa\Matcher\View\ResultViewModelBuilder;

final class Plugin
{
    private const PURGE_HOOK = 'skilitsa_matcher_purge';
    private const RETENTION_DAYS = 90;

    public function __construct(private readonly string $pluginFile)
    {
    }

    public function boot(): void
    {
        $assets   = new Assets($this->pluginFile);
        $sessions = new SessionRepository();

        add_action('init', [$assets, 'register']);

        $shortcode = new QuizShortcode($assets);
        $shortcode->register();

        // The block renders through the same shortcode object: one definition
        // of the markup, so the two cannot drift apart.
        (new QuizBlock($shortcode, $this->pluginFile))->register();
        (new ResultsRoute($sessions, $assets))->register();

        add_action('rest_api_init', function () use ($sessions): void {
            (new MatchController(
                new ContentProvider(),
                $sessions,
                new ResultViewModelBuilder(),
                new EventRecorder(),
            ))->register();
        });

        // Anonymous results expire after 90 days (spec 6).
        add_action(self::PURGE_HOOK, static function () use ($sessions): void {
            $sessions->purgeOlderThan(self::RETENTION_DAYS);
        });
    }

    /** Flushes rewrite rules once so /match/<token> resolves immediately. */
    public static function activate(): void
    {
        $route = new ResultsRoute(new SessionRepository(), new Assets(__FILE__));
        $route->addRewrite();
        flush_rewrite_rules(false);

        if (!wp_next_scheduled(self::PURGE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK);
        }
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules(false);

        $timestamp = wp_next_scheduled(self::PURGE_HOOK);
        if ($timestamp !== false) {
            wp_unschedule_event($timestamp, self::PURGE_HOOK);
        }
    }
}
