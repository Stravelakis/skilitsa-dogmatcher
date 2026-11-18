<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Content;

use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Data\Settings;
use Skilitsa\Core\Import\ImportExecutor;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\MatchService;
use Skilitsa\Core\WordPress\BreedPostType;

/**
 * Everything the matcher needs to read out of core, cached per request.
 *
 * A single seam between the matcher and the database keeps the controller and
 * the view model free of queries, and means the breed display data is fetched
 * once per page rather than once per card.
 */
final class ContentProvider
{
    /** @var array<string,array{name:string,image:?string,permalink:?string}>|null */
    private ?array $breedDisplay = null;
    /** @var array<string,string>|null */
    private ?array $templates = null;
    /** @var array<string,array<string,string>>|null */
    private ?array $traitCopy = null;
    /** @var array<string,string>|null */
    private ?array $uiTexts = null;
    /** @var list<array<string,mixed>>|null */
    private ?array $questions = null;

    public function matchService(): MatchService
    {
        return skilitsa_core()->matchService();
    }

    public function scoringConfig(): ScoringConfig
    {
        return skilitsa_core()->scoringConfig();
    }

    public function flowMode(): string
    {
        $stored = get_option(Settings::OPTION_NAME, []);
        $mode   = is_array($stored) ? (string) ($stored['flow_mode'] ?? 'all') : 'all';

        return in_array($mode, Settings::FLOW_MODES, true) ? $mode : 'all';
    }

    /**
     * Breed name, image and permalink, keyed by canonical breed key.
     *
     * One query plus one thumbnail lookup per breed, not per card.
     *
     * @return array<string,array{name:string,image:?string,permalink:?string}>
     */
    public function breedDisplay(): array
    {
        if ($this->breedDisplay !== null) {
            return $this->breedDisplay;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_title FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_status = 'publish'",
                BreedPostType::POST_TYPE
            ),
            ARRAY_A
        );

        $display = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id  = (int) $row['ID'];
            $key = (string) $row['post_name'];

            // Not upscaling - measured at 131x98 from a 300x164 source. It looks
        // soft because a wide image is cropped to the card's 4:3 and then shown
        // on a high-DPI screen, so the effective density is roughly half what
        // it needs. A larger source fixes it at every card size.
        $thumb = get_the_post_thumbnail_url($id, 'large');

            // Post titles are article headlines - "Τσιουάουα: Ο Μεξικανός
            // Βασιλιάς σε Συσκευασία Τσέπης" - which read well above an article
            // and badly on a result card. The importer stores the plain breed
            // name from the sheet; prefer it and fall back to the title.
            $name = trim((string) get_post_meta($id, ImportExecutor::DISPLAY_NAME_META, true));
            if ($name === '') {
                $name = (string) $row['post_title'];
            }

            $display[$key] = [
                'name'      => $name,
                'image'     => is_string($thumb) && $thumb !== '' ? $thumb : null,
                'permalink' => get_permalink($id) ?: null,
            ];
        }

        return $this->breedDisplay = $display;
    }

    /** @return array<string,string> */
    public function templates(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }

        global $wpdb;
        $table = Schema::tableNames($wpdb->prefix)['explanation_templates'];

        $rows = $wpdb->get_results("SELECT template_key, body FROM {$table}", ARRAY_A);

        $templates = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $templates[(string) $row['template_key']] = (string) $row['body'];
        }

        return $this->templates = $templates;
    }

    /** @return array<string,array<string,string>> */
    public function traitCopy(): array
    {
        if ($this->traitCopy !== null) {
            return $this->traitCopy;
        }

        global $wpdb;
        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $rows = $wpdb->get_results(
            "SELECT trait_key, label, description, scale_low, scale_high,
                    warning_too_low, warning_too_high
             FROM {$table} ORDER BY sort_order, trait_key",
            ARRAY_A
        );

        $copy = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $copy[(string) $row['trait_key']] = array_map('strval', $row);
        }

        return $this->traitCopy = $copy;
    }

    /** @return array<string,string> */
    public function uiTexts(): array
    {
        if ($this->uiTexts !== null) {
            return $this->uiTexts;
        }

        // UI_TEXTS lives in the templates table alongside the explanation copy;
        // both are key/value Greek strings and separating them would mean two
        // tables with identical shapes.
        $texts = [];
        foreach ($this->templates() as $key => $body) {
            if (!str_starts_with($key, 'pos_') && !str_starts_with($key, 'neg_')) {
                $texts[$key] = $body;
            }
        }

        return $this->uiTexts = $texts;
    }

    public function uiText(string $key, string $greekFallback): string
    {
        $value = trim((string) ($this->uiTexts()[$key] ?? ''));

        return $value !== '' ? $value : $greekFallback;
    }

    /**
     * Questions for the active flow mode, in order.
     *
     * @return list<array<string,mixed>>
     */
    public function questionsForFlow(): array
    {
        if ($this->questions !== null) {
            return $this->questions;
        }

        global $wpdb;
        $table = Schema::tableNames($wpdb->prefix)['questions'];

        $where = $this->flowMode() === 'tiered' ? "WHERE tier = 'core'" : '';

        $rows = $wpdb->get_results(
            "SELECT question_key, section_key, label, label_low, label_high,
                    helper_text, image_low, image_high, tier, sort_order,
                    show_dealbreaker, show_ignore
             FROM {$table} {$where}
             ORDER BY sort_order, question_key",
            ARRAY_A
        );

        $questions = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $questions[] = [
                'key'       => (string) $row['question_key'],
                'section'   => (string) $row['section_key'],
                'label'     => (string) $row['label'],
                'labelLow'  => (string) $row['label_low'],
                'labelHigh' => (string) $row['label_high'],
                'helper'    => (string) $row['helper_text'],
                'tier'      => (string) $row['tier'],
                // Several questions have a scale end that already means "no
                // preference"; on those the ignore button says the same thing
                // twice and the reader has to work out the difference.
                'showDealbreaker' => (int) ($row['show_dealbreaker'] ?? 1) === 1,
                'showIgnore'      => (int) ($row['show_ignore'] ?? 1) === 1,
            ];
        }

        return $this->questions = $questions;
    }
}
