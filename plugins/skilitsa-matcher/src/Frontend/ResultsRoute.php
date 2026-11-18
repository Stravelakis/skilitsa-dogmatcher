<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Frontend;

use Skilitsa\Matcher\Session\SessionRepository;
use Skilitsa\Matcher\Session\Token;

/**
 * /match/<token> — a shareable result page.
 *
 * These pages are per-user, so they are noindex and excluded from full-page
 * caching. Spec section 6 is explicit about the second one: LiteSpeed caching a
 * result page would serve one visitor's matches to another, which is a privacy
 * incident rather than a bug.
 */
final class ResultsRoute
{
    public const QUERY_VAR = 'skilitsa_match_token';

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly Assets $assets,
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'addRewrite']);
        add_filter('query_vars', [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'maybeRender']);
    }

    public function addRewrite(): void
    {
        add_rewrite_rule(
            '^match/([a-z0-9]{12})/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /**
     * @param array<int,string> $vars
     * @return array<int,string>
     */
    public function addQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public function maybeRender(): void
    {
        $token = get_query_var(self::QUERY_VAR);
        if (!is_string($token) || $token === '') {
            return;
        }

        if (!Token::isValid($token) || $this->sessions->find($token) === null) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);

            return;
        }

        $this->preventCaching();

        $this->assets->enqueue();

        add_filter('wp_robots', static function (array $robots): array {
            // A result page carries someone's lifestyle answers. It is
            // shareable by link, never by search.
            $robots['noindex']  = true;
            $robots['nofollow'] = true;

            return $robots;
        });

        status_header(200);

        include $this->template();
        exit;
    }

    /**
     * Belt and braces against every caching layer on this stack.
     *
     * LiteSpeed is the one that matters here, but a CDN or a page-cache plugin
     * added later would do the same damage, so the plain HTTP headers go out
     * too rather than trusting one vendor's hook.
     */
    private function preventCaching(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
            header('Pragma: no-cache');
            header('X-Skilitsa-No-Cache: result-page');
        }

        // LiteSpeed Cache is active on this site.
        do_action('litespeed_control_set_nocache', 'Skilitsa result pages are per-user.');

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        nocache_headers();
    }

    private function template(): string
    {
        $theme = locate_template(['skilitsa-matcher/results.php']);

        return $theme !== '' ? $theme : dirname(__DIR__, 2) . '/templates/results.php';
    }
}
