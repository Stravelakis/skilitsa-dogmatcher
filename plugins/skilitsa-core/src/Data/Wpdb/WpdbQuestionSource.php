<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Wpdb;

use Skilitsa\Core\Data\Mapper\LinkMapper;
use Skilitsa\Core\Data\Mapper\QuestionMapper;
use Skilitsa\Core\Data\QuestionSource;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;

final class WpdbQuestionSource implements QuestionSource
{
    /** @var list<Question>|null */
    private ?array $questionCache = null;
    /** @var list<QuestionTraitLink>|null */
    private ?array $linkCache = null;

    public function __construct(
        private readonly QuestionMapper $questionMapper = new QuestionMapper(),
        private readonly LinkMapper $linkMapper = new LinkMapper(),
    ) {
    }

    public function questions(): array
    {
        if ($this->questionCache !== null) {
            return $this->questionCache;
        }

        global $wpdb;
        $table = Schema::tableNames($wpdb->prefix)['questions'];

        $rows = $wpdb->get_results(
            "SELECT question_key, weight FROM {$table} ORDER BY sort_order, question_key",
            ARRAY_A
        );

        return $this->questionCache = $this->questionMapper->fromRows(is_array($rows) ? $rows : []);
    }

    public function links(): array
    {
        if ($this->linkCache !== null) {
            return $this->linkCache;
        }

        global $wpdb;
        $table = Schema::tableNames($wpdb->prefix)['question_trait_map'];

        $rows = $wpdb->get_results(
            "SELECT question_key, trait_key, direction, weight
             FROM {$table}
             ORDER BY question_key, trait_key",
            ARRAY_A
        );

        return $this->linkCache = $this->linkMapper->fromRows(is_array($rows) ? $rows : []);
    }

    /** @return list<string> Data-quality problems found while mapping. */
    public function skipped(): array
    {
        return array_merge($this->questionMapper->skipped(), $this->linkMapper->skipped());
    }
}
