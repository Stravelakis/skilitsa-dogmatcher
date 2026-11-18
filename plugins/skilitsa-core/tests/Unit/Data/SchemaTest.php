<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\Schema;

final class SchemaTest extends TestCase
{
    public function test_table_names_are_prefixed(): void
    {
        $names = Schema::tableNames('wp_');

        self::assertSame('wp_skilitsa_breed_traits', $names['breed_traits']);
        self::assertSame('wp_skilitsa_trait_definitions', $names['trait_definitions']);
        self::assertSame('wp_skilitsa_question_trait_map', $names['question_trait_map']);
    }

    public function test_every_logical_table_is_named(): void
    {
        $expected = [
            'breed_traits',
            'trait_definitions',
            'questions',
            'question_trait_map',
            'explanation_templates',
            'match_sessions',
            'match_answers',
            'match_results',
            'match_events',
            'ai_drafts',
        ];

        self::assertSame($expected, array_keys(Schema::tableNames('wp_')));
    }

    public function test_a_create_statement_exists_for_every_table(): void
    {
        $statements = Schema::createStatements('wp_', 'DEFAULT CHARSET=utf8mb4');

        self::assertCount(count(Schema::tableNames('wp_')), $statements);

        foreach (Schema::tableNames('wp_') as $table) {
            $found = false;
            foreach ($statements as $sql) {
                if (str_contains($sql, $table)) {
                    $found = true;
                    break;
                }
            }
            self::assertTrue($found, "No CREATE TABLE for {$table}");
        }
    }

    public function test_statements_are_dbdelta_compatible(): void
    {
        // dbDelta is famously picky: it needs "CREATE TABLE" uppercase, two
        // spaces before PRIMARY KEY, each field on its own line, and KEY
        // rather than INDEX.
        foreach (Schema::createStatements('wp_', 'DEFAULT CHARSET=utf8mb4') as $sql) {
            self::assertStringStartsWith('CREATE TABLE ', $sql);
            self::assertStringContainsString('  PRIMARY KEY  (', $sql);
            self::assertStringNotContainsString('INDEX ', $sql);
            self::assertStringEndsWith('DEFAULT CHARSET=utf8mb4', $sql);
        }
    }

    public function test_breed_traits_carries_an_index_on_both_lookup_columns(): void
    {
        $sql = $this->statementFor('breed_traits');

        self::assertStringContainsString('breed_id', $sql);
        self::assertStringContainsString('trait_key', $sql);
        self::assertStringContainsString('KEY breed_id', $sql);
        self::assertStringContainsString('KEY trait_key', $sql);
    }

    public function test_questions_carry_the_two_affordance_switches(): void
    {
        // The sheet has always had these columns and nothing read them, so a
        // question could not turn off an affordance that made no sense on it.
        $sql = $this->statementFor('questions');

        self::assertStringContainsString('show_dealbreaker', $sql);
        self::assertStringContainsString('show_ignore', $sql);
    }

    public function test_trait_definitions_carries_the_asymmetry_columns(): void
    {
        $sql = $this->statementFor('trait_definitions');

        self::assertStringContainsString('penalty_over', $sql);
        self::assertStringContainsString('penalty_under', $sql);
        self::assertStringContainsString('global_weight', $sql);
        self::assertStringContainsString('warning_too_low', $sql);
        self::assertStringContainsString('warning_too_high', $sql);

        // is_tuned is what stops an import from overwriting a human judgement
        // with the code default.
        self::assertStringContainsString('is_tuned', $sql);
    }

    public function test_question_trait_map_has_no_answer_value_column(): void
    {
        // v1 joined on answer_value, which the source sheet never populated.
        // Its absence here is deliberate and load-bearing.
        self::assertStringNotContainsString('answer_value', $this->statementFor('question_trait_map'));
        self::assertStringContainsString('direction', $this->statementFor('question_trait_map'));
    }

    public function test_match_sessions_carries_the_deferred_account_seam(): void
    {
        $sql = $this->statementFor('match_sessions');

        // Accounts are phase 3, but the nullable column costs nothing now and
        // saves a migration later (spec section 2).
        self::assertStringContainsString('user_uuid', $sql);
        self::assertStringContainsString('terms_version', $sql);
        self::assertStringContainsString('terms_accepted_at', $sql);
        self::assertStringContainsString('result_snapshot', $sql);
    }

    public function test_version_is_a_plain_version_string(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Schema::VERSION);
    }

    private function statementFor(string $logical): string
    {
        $table = Schema::tableNames('wp_')[$logical];

        foreach (Schema::createStatements('wp_', 'DEFAULT CHARSET=utf8mb4') as $sql) {
            if (str_contains($sql, $table)) {
                return $sql;
            }
        }

        self::fail("No statement for {$logical}");
    }
}
