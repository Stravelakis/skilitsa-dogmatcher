<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Telemetry;

use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Engine\Value\MatchResult;
use Skilitsa\Matcher\Session\Token;

/**
 * Anonymous session telemetry (spec 7).
 *
 * Records a random token, the answers and the resulting ranking. Deliberately
 * records NO IP, NO user agent and NO user ID - the design minimises
 * identifiers so the data can support weight tuning and, later, adaptive mode,
 * without becoming a privacy liability.
 */
final class EventRecorder
{
    private const ALLOWED_EVENTS = [
        'quiz_started',
        'question_answered',
        'quiz_completed',
        'card_clicked',
        'breed_link_clicked',
        'more_results_opened',
        'excluded_opened',
        'dealbreaker_relaxed',
    ];

    public function record(string $token, string $type, string $target = ''): void
    {
        if (!Token::isValid($token) || !in_array($type, self::ALLOWED_EVENTS, true)) {
            return;
        }

        global $wpdb;

        $wpdb->insert(Schema::tableNames($wpdb->prefix)['match_events'], [
            'token'      => $token,
            'event_type' => $type,
            // Bounded so a crafted payload cannot fill the column.
            'target'     => mb_substr($target, 0, 191),
            'created_at' => current_time('mysql', true),
        ]);
    }

    public function recordCompletion(string $token, MatchResult $result): void
    {
        $this->record(
            $token,
            'quiz_completed',
            sprintf('%d traits, %d ranked', $result->traitsKnown, count($result->ranked))
        );
    }
}
