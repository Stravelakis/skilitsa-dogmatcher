<?php
declare(strict_types=1);

namespace Skilitsa\Core\WordPress;

use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Data\Installer;
use Skilitsa\Core\Data\Settings;
use Skilitsa\Core\Data\Wpdb\WpdbBreedSource;
use Skilitsa\Core\Data\Wpdb\WpdbQuestionSource;
use Skilitsa\Core\Data\Wpdb\WpdbTraitSource;
use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\MatchService;

final class Plugin
{
    private ?MatchService $matchService = null;
    private ?WpdbTraitSource $traitSource = null;
    private ?WpdbQuestionSource $questionSource = null;
    private ?WpdbBreedSource $breedSource = null;

    public function boot(): void
    {
        add_action('init', [$this, 'onInit']);

        if (is_admin()) {
            (new AdminMenu())->register();
        }
    }

    public function onInit(): void
    {
        // Checked on every request, not just in wp-admin: this plugin deploys
        // by file copy, so a schema bump would otherwise never apply on a site
        // whose owner does not visit wp-admin - and a missing table degrades
        // silently to "no breeds" rather than erroring.
        Installer::maybeUpgrade();

        (new BreedPostType())->register();

        // Breeds stopped being posts, so everything that asks for "posts in a
        // category" stopped finding them. This lets them answer anyway.
        (new BreedQueryBridge())->register();
    }

    /**
     * The shared MatchService. Feature plugins call this rather than
     * constructing their own, so the per-request source caches are shared.
     */
    public function matchService(): MatchService
    {
        if ($this->matchService === null) {
            $this->matchService = new MatchService(
                $this->traitSource(),
                $this->questionSource(),
                $this->breedSource(),
                MatchEngine::create(),
            );
        }

        return $this->matchService;
    }

    public function scoringConfig(): ScoringConfig
    {
        $stored = get_option(Settings::OPTION_NAME, []);

        return Settings::scoringConfigFrom(is_array($stored) ? $stored : []);
    }

    /**
     * Everything the mappers refused to load this request, for the admin
     * data-quality report. v1 swallowed these silently.
     *
     * @return list<string>
     */
    public function dataProblems(): array
    {
        // The mappers only populate their skipped list while mapping, so the
        // sources must actually be read before asking. Each caches per request,
        // so this is free when a match has already run.
        $this->traitSource()->traits();
        $this->questionSource()->questions();
        $this->questionSource()->links();
        $this->breedSource()->breeds();

        return array_merge(
            $this->traitSource()->skipped(),
            $this->questionSource()->skipped(),
            $this->breedSource()->skipped(),
        );
    }

    private function traitSource(): WpdbTraitSource
    {
        return $this->traitSource ??= new WpdbTraitSource();
    }

    private function questionSource(): WpdbQuestionSource
    {
        return $this->questionSource ??= new WpdbQuestionSource();
    }

    private function breedSource(): WpdbBreedSource
    {
        return $this->breedSource ??= new WpdbBreedSource();
    }
}
