<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Session;

use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Engine\Value\MatchResult;

/**
 * Persists quiz sessions and their computed results.
 *
 * Storing the result at the token rather than recomputing on view means a
 * shared link shows what the sender saw, even after breed data changes
 * (spec 4.8).
 *
 * Records no IP, no user agent and no user ID (spec 7).
 */
final class SessionRepository
{
    public function find(string $token): ?MatchSession
    {
        if (!Token::isValid($token)) {
            return null;
        }

        global $wpdb;
        $tables = Schema::tableNames($wpdb->prefix);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['match_sessions']} WHERE token = %s", $token),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $answerRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_key, answer, is_dealbreaker, is_ignored
                 FROM {$tables['match_answers']} WHERE token = %s",
                $token
            ),
            ARRAY_A
        );

        $answers = $dealbreakers = $ignored = [];

        foreach (is_array($answerRows) ? $answerRows : [] as $answerRow) {
            $key = (string) $answerRow['question_key'];

            $answers[$key] = (int) $answerRow['answer'];

            if ((int) $answerRow['is_dealbreaker'] === 1) {
                $dealbreakers[] = $key;
            }
            if ((int) $answerRow['is_ignored'] === 1) {
                $ignored[] = $key;
            }
        }

        return new MatchSession(
            token: $token,
            answers: $answers,
            dealbreakers: $dealbreakers,
            ignored: $ignored,
            flowMode: (string) $row['flow_mode'],
            termsVersion: (string) $row['terms_version'],
            userUuid: $row['user_uuid'] !== null ? (string) $row['user_uuid'] : null,
            resultSnapshot: $row['result_snapshot'] !== null ? (string) $row['result_snapshot'] : null,
        );
    }

    public function save(MatchSession $session): void
    {
        global $wpdb;
        $tables = Schema::tableNames($wpdb->prefix);
        $now    = current_time('mysql', true);

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['match_sessions']} WHERE token = %s",
            $session->token
        ));

        if ($exists === 0) {
            $wpdb->insert($tables['match_sessions'], [
                'token'             => $session->token,
                'user_uuid'         => $session->userUuid,
                'flow_mode'         => $session->flowMode,
                'terms_version'     => $session->termsVersion,
                'terms_accepted_at' => $session->termsVersion !== '' ? $now : null,
                'started_at'        => $now,
            ]);
        }

        // Answers are replaced wholesale: the client always posts its complete
        // current state, so a diff would only add ways to disagree with it.
        $wpdb->delete($tables['match_answers'], ['token' => $session->token]);

        $dealbreakers = array_fill_keys($session->dealbreakers, true);
        $ignored      = array_fill_keys($session->ignored, true);

        foreach ($session->answers as $questionKey => $answer) {
            $wpdb->insert($tables['match_answers'], [
                'token'          => $session->token,
                'question_key'   => (string) $questionKey,
                'answer'         => (int) $answer,
                'is_dealbreaker' => isset($dealbreakers[$questionKey]) ? 1 : 0,
                'is_ignored'     => isset($ignored[$questionKey]) ? 1 : 0,
            ]);
        }
    }

    /** Stores the ranking and a snapshot so the URL is reproducible. */
    public function saveResult(string $token, MatchResult $result, string $snapshot): void
    {
        global $wpdb;
        $tables = Schema::tableNames($wpdb->prefix);

        $wpdb->update(
            $tables['match_sessions'],
            [
                'completed_at'    => current_time('mysql', true),
                'result_snapshot' => $snapshot,
            ],
            ['token' => $token]
        );

        $wpdb->delete($tables['match_results'], ['token' => $token]);

        foreach ($result->top(10) as $rank => $match) {
            $wpdb->insert($tables['match_results'], [
                'token'         => $token,
                'breed_id'      => 0,
                'rank_position' => $rank + 1,
                'score'         => $match->score,
            ]);
        }
    }

    /** Anonymous results expire after 90 days (spec 6). Returns rows removed. */
    public function purgeOlderThan(int $days): int
    {
        global $wpdb;
        $tables = Schema::tableNames($wpdb->prefix);

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT token FROM {$tables['match_sessions']}
             WHERE user_uuid IS NULL AND started_at < %s",
            $cutoff
        ));

        if (!is_array($tokens) || $tokens === []) {
            return 0;
        }

        foreach ($tokens as $token) {
            foreach (['match_answers', 'match_results', 'match_events'] as $table) {
                $wpdb->delete($tables[$table], ['token' => $token]);
            }
            $wpdb->delete($tables['match_sessions'], ['token' => $token]);
        }

        return count($tokens);
    }
}
