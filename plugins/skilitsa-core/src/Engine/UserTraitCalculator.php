<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\UserTraitProfile;

/**
 * Turns raw answers into a trait profile on the same 1-5 scale breeds use.
 *
 * Spec section 4.2. The two weights multiply:
 *   question.weight - how much this question matters at all
 *   link.weight     - how strongly it informs THIS trait
 */
final class UserTraitCalculator
{
    private const MIN = 1.0;
    private const MAX = 5.0;
    /** An answer of 1 inverts to 5, 5 inverts to 1: 6 - answer. */
    private const INVERSION_PIVOT = 6.0;

    /**
     * @param list<Question>          $questions
     * @param list<QuestionTraitLink> $links
     */
    public function calculate(AnswerSet $answers, array $questions, array $links): UserTraitProfile
    {
        $questionWeights = [];
        foreach ($questions as $question) {
            $questionWeights[$question->key] = $question->weight;
        }

        $numerators   = [];
        $denominators = [];

        foreach ($links as $link) {
            if (!array_key_exists($link->questionKey, $questionWeights)) {
                continue;
            }

            $answer = $answers->answerFor($link->questionKey);
            if ($answer === null) {
                continue;
            }

            $weight = $questionWeights[$link->questionKey] * $link->weight;
            if ($weight <= 0.0) {
                continue;
            }

            $value = $link->isInverse()
                ? self::INVERSION_PIVOT - (float) $answer
                : (float) $answer;

            $numerators[$link->traitKey]   = ($numerators[$link->traitKey] ?? 0.0) + $value * $weight;
            $denominators[$link->traitKey] = ($denominators[$link->traitKey] ?? 0.0) + $weight;
        }

        $values = [];
        foreach ($numerators as $traitKey => $numerator) {
            $denominator = $denominators[$traitKey];
            if ($denominator <= 0.0) {
                continue;
            }
            $values[$traitKey] = $this->clamp($numerator / $denominator);
        }

        return new UserTraitProfile($values);
    }

    private function clamp(float $value): float
    {
        return max(self::MIN, min(self::MAX, $value));
    }
}
